/**
 * Telling an icon that draws from one that does not.
 *
 * Semantic UI draws an icon as the `::before` content of the rule its name selects, `i.icon.paint.brush:before` for
 * `<i class="paint brush icon">`. A name the build has no rule for selects nothing, so the icon stays empty: no error,
 * no failed request, only a blank where it should be. Sylius builds on Semantic UI 2.5, whose icon set is older than
 * Font Awesome's and Fomantic UI's, so a name taken from their lists may well be missing (the designs index named
 * `palette`, and showed an empty circle). Whether an icon draws is therefore read off the content the browser computes
 * for it.
 */

/**
 * The icons within the given page or element that draw nothing, by their class names
 *
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} scope
 * @returns {Promise<string[]>}
 */
async function blankIcons(scope) {
    return scope.locator('i.icon').evaluateAll((icons) =>
        icons
            .filter((icon) => ['none', 'normal', '""'].includes(getComputedStyle(icon, '::before').content))
            .map((icon) => icon.className),
    );
}

module.exports = { blankIcons };
