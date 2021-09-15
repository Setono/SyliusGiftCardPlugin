@buying_gift_card
Feature: Adding a configurable gift card to the cart
  In order to chose how much I want to spend on a gift card
  As a Customer
  I want to be able to buy a gift card with custom amount

  Background:
    Given the store operates on a single channel in "United States"
    And the store has a product "Gift card"
    And this product is a configurable gift card

  @ui
  Scenario: Adding a configurable gift card to the cart
    Given I am a logged in customer
    When I add this product to the cart with amount "$125.00" and custom message "Hey buddy"
    Then I should be on my cart summary page
    And I should be notified that the product has been successfully added
    And there should be one item in my cart
    And this item should have name "Gift card"
    And total price of "Gift card" item should be "$125.00"
