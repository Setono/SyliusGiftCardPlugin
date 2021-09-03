@managing_gift_cards
Feature: Deleting an existing gift card
  In order to manage the existing gift cards
  As an Administrator
  I want to delete an existing gift card

  Background:
    Given the store operates on a channel named "United States" in "USD" currency
    And I am logged in as an administrator
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"

  @api
  Scenario: Deleting gift card
    When I delete the gift card "GIFT-CARD-100"
    Then I should be notified that it has been successfully deleted
    And I should no longer see a gift card with code "GIFT-CARD-100"
