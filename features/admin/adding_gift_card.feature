@managing_gift_cards
Feature: Creating a gift card
  In order to have gift cards in the shop
  As an Administrator
  I want to create a new gift card

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator
    And there is a customer account "john-doe@setono.com"

  @api
  Scenario: Creating a gift card
    When I want to create a new gift card
    And I specify its code as "GIFT-CARD-100"
    And I do not specify its customer
    And I specify its amount as "$100"
    And I specify its currency code as "USD"
    And I specify its channel as "United States"
    And I add it
    Then I should be notified that it has been successfully created
    And I should see a gift card with code "GIFT-CARD-100" valued at "$100"
    And this gift card should have api origin

  @api
  Scenario: Creating a gift card for a customer
    When I want to create a new gift card
    And I specify its code as "GIFT-CARD-100"
    And I specify its customer as "john-doe@setono.com"
    And I specify its amount as "$100"
    And I specify its currency code as "USD"
    And I specify its channel as "United States"
    And I add it
    Then I should be notified that it has been successfully created
    And I should see a gift card with code "GIFT-CARD-100" valued at "$100" associated to the customer "john-doe@setono.com"
    And this gift card should have api origin
