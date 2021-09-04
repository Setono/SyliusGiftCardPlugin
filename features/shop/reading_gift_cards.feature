@managing_shop_gift_cards
Feature: Reading an existing gift card
  In order to see the existing gift cards
  I want to read an existing gift card by its code

  Background:
    Given the store operates on a channel named "United States" in "USD" currency
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"

  @api
  Scenario: Browsing gift cards
    When I open the gift card "GIFT-CARD-100" page
    Then It should be valued at "$100"
    And It should be initially valued at "$100"
    And It should have "USD" currency
