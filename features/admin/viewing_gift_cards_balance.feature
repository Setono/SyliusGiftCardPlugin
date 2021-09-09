@managing_gift_cards_balance
Feature: Viewing gift cards balance
  In order to get a view of edited gift cards amount
  As an Administrator
  I want to see gift cards balance

  Background:
    Given the store has currency "USD"
    And the store has currency "EUR"
    And the store operates on a channel named "United States" in "USD" currency
    And that channel allows to shop using "EUR" and "USD" currencies
    And the store operates on a channel named "Europe" in "EUR" currency
    And that channel allows to shop using "EUR" and "USD" currencies
    And I am logged in as an administrator
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100" on channel "United States"
    And the store has a gift card with code "GIFT-CARD-200" valued at "$200" on channel "United States"
    And the store has a gift card with code "GIFT-CARD-50" valued at "€50" on channel "Europe"

  @api
  Scenario: Browsing gift cards
    When I browse gift cards balance
    Then I should see a gift card balance of "$300" in "USD" currency
    And I should see a gift card balance of "€50" in "EUR" currency
