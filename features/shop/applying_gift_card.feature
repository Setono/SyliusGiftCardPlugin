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
    And the store has a gift card with code "GIFT-CARD-200" valued at "$200"
    And the store ships everywhere for free
    And the store allows paying offline
    And there is a user "john@example.com" identified by "password123"
    And I am logged in as "john@example.com"

  @ui
  Scenario: Placing an order with a gift card
    Given I have product "PHP T-Shirt" in the cart
    And I have applied gift card "GIFT-CARD-100"
    And I proceed selecting "United States" as shipping country
    And I complete the shipping step
    And I confirm my order
    Then I should see the thank you page
    And The gift card "GIFT-CARD-100" should be disabled
