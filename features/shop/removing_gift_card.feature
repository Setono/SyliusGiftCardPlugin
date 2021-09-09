@applying_gift_card
Feature: Applying gift card
  In order to pay proper amount after using the gift card
  As a Customer
  I want to have gift card applied to my cart

  Background:
    Given the store operates on a single channel in "United States"
    And the store has a product "PHP T-Shirt" priced at "$100.00"
    And the store has a product "Javascript T-Shirt" priced at "$1"
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"
    And the store ships everywhere for free
    And the store allows paying offline
    And I have product "PHP T-Shirt" in the cart
    And I have product "Javascript T-Shirt" in the cart
    And My cart has gift card with code "GIFT-CARD-100"

  @api
  Scenario: Buying a gift card
    Given I am a logged in customer
    And I remove gift card with code "GIFT-CARD-100"
    When I proceed through checkout process
    And I confirm my order
    Then I should see the thank you page
    And the gift card "GIFT-CARD-100" should still be enabled
