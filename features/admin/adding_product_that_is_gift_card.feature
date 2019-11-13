@managing_gift_cards
Feature: Adding a new product that is a gift card
  In order to help frustrated relatives with their gift shopping
  As an Administrator
  I want to add a new gift card product to the shop

  Background:
    Given the store operates on a single channel in "United States"
    And the store has "Standard" shipping category
    And I am logged in as an administrator

  @ui
  Scenario: Adding a new gift card product
    Given I want to create a new simple product
    When I specify its code as "gift_card_100"
    And I name it "Gift Card $100" in "English (United States)"
    And I set its slug to "gift-card-100" in "English (United States)"
    And I set its price to "$100.00" for "United States" channel
    And I set its gift card value to true
    And I add it
    Then I should be notified that it has been successfully created
    And the product "Gift Card $100" should appear in the store
    And the product "Gift Card $100" should be a gift card
