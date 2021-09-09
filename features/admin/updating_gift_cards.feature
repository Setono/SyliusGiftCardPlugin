@managing_gift_cards
Feature: Updating an existing gift card
  In order to manage the existing gift cards
  As an Administrator
  I want to update an existing gift card

  Background:
    Given the store operates on a channel named "United States" in "USD" currency
    And I am logged in as an administrator
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"

  @api
  Scenario: Updating gift card
    When I want to edit the gift card "GIFT-CARD-100"
    And I do not specify its customer
    And I specify its amount as "$150"
    And I specify its currency code as "USD"
    And I specify its channel as "United States"
    And I disable it
    And I save my changes
    Then I should be notified that it has been successfully updated
    And It should be valued at "$150"
    And It should be disabled
