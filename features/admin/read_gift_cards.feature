@managing_gift_cards
Feature: Reading existing gift card
  In order to manage the existing gift cards
  As an Administrator
  I want to read an existing gift card

  Background:
    Given the store operates on a channel named "United States" in "USD" currency
    And I am logged in as an administrator
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"

  @api
  Scenario: Browsing gift cards
    When I open the gift card "GIFT-CARD-100" page
    Then It should be valued at "$100"
    And It should be initially valued at "$100"
    And It should be on channel "United States"
    And It should have "USD" currency
