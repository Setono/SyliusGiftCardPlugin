# Usage

## Gift cards configurations

When accessing to the route `/admin/gift-card-configurations`, you can see the list of all gift card configurations.
A gift card configuration is a set of rules that will be applied to the gift card when it is created.
It can be associated to one or many channels and locales, and it can also be the default configuration to use everywhere.

A gift card configuration mainly contains information about how to render the PDF document, or the default validity period.

## Gift card product

In order for your customers to be able to buy a gift card, and then use it, you must first create a product marked as gift card.
Head to `/admin/products` and click on the `Add product` button.

### Simple vs Configurable

When creating a new GC product, you have 2 choices:
1. Simple gift card: it means that you as an administrator define the price, and the customer can only buy a gift card of that value.
2. Configurable gift card: it means the customer can buy a gift card of any value, they will define themselves the price before adding to their cart.

Make sure to check the toggle `gift card` in `Details` tab, and also the configurable amount if you created a configurable one.

## Gift cards

When accessing to the route `/admin/gift-cards`, you can see the list of all gift cards.
A gift card is composed of a code, an amount (with a currency) and an expiration date.
It can also be associated to a customer, and be enabled/disabled.

### Adding a gift card

There are 2 ways of adding a new gift card:

#### Manually creating a gift card as an administrator

Any administrator can create a new gift card for a given channel. They need to define the amount, and then validate the form.
The gift card will be marked as manually created in the admin panel, but can be used as any other gift card.

#### Buying a gift card as a customer

Customers can buy gift cards in the store if there is any product marked as gift card. As soon as the customer pays for it in the checkout process,
it will create a gift card of the same value and send email to the customer containing the code.
