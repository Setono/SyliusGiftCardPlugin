@managing_gift_cards
Feature: Browsing existing gift cards
  In order to manage the existing gift cards
  As an Administrator
  I want to browse existing gift cards

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100"
    And the store has a gift card with code "GIFT-CARD-200" valued at "$200"

  @api
  Scenario: Browsing gift cards
    When I browse gift cards
    Then I should see a gift card with code "GIFT-CARD-100" valued at "$100"
    And I should see a gift card with code "GIFT-CARD-200" valued at "$200"
