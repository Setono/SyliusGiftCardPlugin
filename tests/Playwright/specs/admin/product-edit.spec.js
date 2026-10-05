const { test, expect } = require('@playwright/test');
const { clickAndConfirm } = require('../support/admin');
const { productIdsByKind } = require('../support/fixtures');
const { clickAndWaitForPage } = require('../support/navigation');

const FORM = 'form[name="sylius_product"]';

/**
 * The product's edit form, checked to be the form of a product rather than merely a page that answered
 *
 * @param {import('@playwright/test').Page} page
 */
async function expectProductForm(page) {
    await expect(page.locator(FORM)).toBeVisible();
    await expect(page.locator(`${FORM} [name="sylius_product[code]"]`)).not.toHaveValue('');
}

/**
 * Deletes the product through the products grid, the way an admin does, and checks it is gone
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 */
async function deleteProduct(page, id) {
    await page.goto(`/admin/products/${id}/edit`);
    const code = await page.locator(`${FORM} [name="sylius_product[code]"]`).inputValue();

    await page.goto(`/admin/products/?criteria[search][value]=${encodeURIComponent(code)}`);
    await clickAndConfirm(page, page.locator(`form[action="/admin/products/${id}"] button[type="submit"]`));

    expect((await page.goto(`/admin/products/${id}/edit`))?.status(), `the product ${code} should have been deleted`).toBe(404);
}

/**
 * Regression cover for the product edit page.
 *
 * The test application used to override Sylius' product details tab to render the gift card checkbox. That
 * override once dropped the remote_url and load_edit_url options Sylius passes to the options autocomplete,
 * and since the form theme reads remote_url unconditionally, every configurable product's edit page returned
 * a 500 — gift card products and ordinary ones alike. Nothing covered these pages, so it went unnoticed until
 * someone opened one by hand.
 */
test.describe('admin product edit', () => {
    /** @type {{simple: string|null, configurable: string|null, giftCard: string|null}} */
    let products;

    // Resolved through the signed in page fixture rather than beforeAll, because a page made straight from
    // the browser would not carry the stored admin session
    test.beforeEach(async ({ page }) => {
        products = await productIdsByKind(page);
    });

    test('renders for a configurable product', async ({ page }) => {
        expect(products.configurable, 'no configurable product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.configurable}/edit`);

        expect(response?.status()).toBe(200);
        await expectProductForm(page);
        // The field whose missing options caused the 500
        await expect(page.locator('.sylius-autocomplete').first()).toBeVisible();
    });

    test('renders for a simple product', async ({ page }) => {
        expect(products.simple, 'no simple product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.simple}/edit`);

        expect(response?.status()).toBe(200);
        await expectProductForm(page);
        // the simple branch of the details tab carries the variant's own fields
        await expect(page.locator(`${FORM} input[name*="[variant]"][name*="[shippingRequired]"]`)).toHaveCount(1);
    });

    test('renders for a gift card product and exposes the gift card toggle', async ({ page }) => {
        expect(products.giftCard, 'no gift card product was seeded').not.toBeNull();

        const response = await page.goto(`/admin/products/${products.giftCard}/edit`);

        expect(response?.status()).toBe(200);
        await expectProductForm(page);
        // every product's form has the toggle, so what matters is that it shows this product as a gift card
        await expect(page.locator('input[name="sylius_product[giftCard]"]')).toBeChecked();
    });

    test('the options autocomplete is given the url it needs', async ({ page }) => {
        expect(products.configurable, 'no configurable product was seeded').not.toBeNull();

        await page.goto(`/admin/products/${products.configurable}/edit`);

        // data-url is rendered from remote_url; empty means the option was dropped again and the page only
        // survives because Twig happened not to be in strict mode
        const dataUrl = await page.locator('.sylius-autocomplete').first().getAttribute('data-url');
        expect(dataUrl, 'the options autocomplete has no remote_url').toBeTruthy();
    });

    /**
     * The action scaffolds a product through the same factory the fixtures use, so a merchant lands on a
     * ready-to-edit gift card product instead of assembling the option and both variants by hand.
     *
     * The product is deleted again, whatever happens in between, so repeated runs do not pile scaffolded products up
     * in the grid the other specs walk
     */
    test('a gift card product can be scaffolded from the gift cards index', async ({ page }) => {
        await page.goto('/admin/gift-cards/');

        // A POST form carrying a CSRF token rather than a link: a link could be hit by a browser prefetch or
        // an <img src> on any page the admin visits, and every hit creates another product
        const form = page.locator('form[action$="/admin/gift-cards/create-product"]');
        await expect(form).toHaveCount(1);
        await expect(form).toHaveAttribute('method', /post/i);
        await expect(form.locator('input[name="_csrf_token"]')).not.toHaveValue('');

        // The button asks for confirmation before anything is created
        await clickAndConfirm(page, form.locator('button[type="submit"]'));

        await expect(page).toHaveURL(/\/admin\/products\/\d+\/edit$/);
        const productId = /** @type {string} */ (/\/admin\/products\/(\d+)\/edit$/.exec(page.url())?.[1]);

        try {
            await expectProductForm(page);

            // Created disabled so the merchant reviews it before it goes live, and flagged as a gift card
            await expect(page.locator('input[name="sylius_product[giftCard]"]')).toBeChecked();
            await expect(page.locator('input[name="sylius_product[enabled]"]')).not.toBeChecked();

            // One variant per delivery type, so the customer can pick virtual or physical straight away
            await page.goto(`/admin/products/${productId}/variants/`);
            await expect(page.locator('[data-test-grid-table-body] tr')).toHaveCount(2);
        } finally {
            await deleteProduct(page, productId);
        }
    });

    /**
     * Scaffolding changes state, so it must not be reachable by a GET (which any page the admin visits could
     * trigger through an <img src> or a prefetch) nor by a POST that does not carry the CSRF token
     */
    test('a gift card product is not scaffolded by a GET or without a CSRF token', async ({ page }) => {
        const get = await page.request.get('/admin/gift-cards/create-product', { maxRedirects: 0 });
        expect(get.status()).toBe(405);

        const post = await page.request.post('/admin/gift-cards/create-product', {
            form: { _csrf_token: 'forged' },
            maxRedirects: 0,
        });
        expect(post.status()).toBe(403);
    });
});

/**
 * The checkbox is rendered through the sylius.admin.product.tab_details UI event rather than a copied Sylius
 * template. Sylius ends the product form with render_rest: false, so a checkbox nothing renders never reaches
 * the request and Symfony submits it as unchecked — every save silently un-flagged the product, including the
 * edit page the scaffolding action lands the merchant on.
 */
test.describe('admin product gift card flag', () => {
    /** @type {{simple: string|null, configurable: string|null, giftCard: string|null}} */
    let products;

    test.beforeEach(async ({ page }) => {
        products = await productIdsByKind(page);
    });

    /** @param {import('@playwright/test').Page} page */
    const checkbox = (page) => page.locator('input[name="sylius_product[giftCard]"]');

    /**
     * Semantic UI keeps the real input behind its label, and the block renders at the bottom of the tab where
     * the debug toolbar sits, so clicking the input is intercepted. The keyboard toggles it without a pointer.
     *
     * @param {import('@playwright/test').Page} page
     * @param {boolean} checked
     */
    async function setChecked(page, checked) {
        if (checked !== (await checkbox(page).isChecked())) {
            await checkbox(page).press('Space');
        }

        await expect(checkbox(page)).toBeChecked({ checked });
    }

    /** @param {import('@playwright/test').Page} page */
    async function save(page) {
        await clickAndWaitForPage(page, page.locator('#sylius_save_changes_button'));
        await expect(page.locator('.sylius-flash-message.positive'), 'the product should have been saved').toBeVisible();
    }

    test('the checkbox is rendered when creating either kind of product', async ({ page }) => {
        for (const path of ['/admin/products/new/simple', '/admin/products/new']) {
            const response = await page.goto(path);

            expect(response?.status(), `${path} did not render`).toBe(200);
            await expect(checkbox(page)).toHaveCount(1);
        }
    });

    test('the checkbox is rendered when editing either kind of product', async ({ page }) => {
        for (const [kind, id] of Object.entries({ simple: products.simple, configurable: products.configurable })) {
            expect(id, `no ${kind} product was seeded`).not.toBeNull();

            await page.goto(`/admin/products/${id}/edit`);
            await expect(checkbox(page)).toHaveCount(1);
        }
    });

    test('ticking the checkbox survives a save', async ({ page }) => {
        // Whichever kind of product happens not to be flagged yet
        let id = null;
        for (const candidate of [products.simple, products.configurable]) {
            if (null === candidate) {
                continue;
            }
            await page.goto(`/admin/products/${candidate}/edit`);
            if (!(await checkbox(page).isChecked())) {
                id = candidate;
                break;
            }
        }
        expect(id, 'every seeded product is already flagged as a gift card').not.toBeNull();

        try {
            await setChecked(page, true);
            await save(page);

            await page.reload();
            await expect(checkbox(page)).toBeChecked();
        } finally {
            // Leave the product as it was found, whatever failed, so the shop specs still meet an ordinary product
            await page.goto(`/admin/products/${id}/edit`);
            await setChecked(page, false);
            await save(page);
        }

        await page.reload();
        await expect(checkbox(page)).not.toBeChecked();
    });

    test('saving an already flagged gift card product keeps the flag', async ({ page }) => {
        expect(products.giftCard, 'no gift card product was seeded').not.toBeNull();

        await page.goto(`/admin/products/${products.giftCard}/edit`);
        await expect(checkbox(page)).toBeChecked();

        await save(page);

        await page.reload();
        await expect(checkbox(page)).toBeChecked();
    });
});
