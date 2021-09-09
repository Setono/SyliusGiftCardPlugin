@managing_shop_gift_cards
Feature: Browsing existing gift cards
  In order to see my gift cards
  As a Shop user
  I want to browse existing gift cards that are associated to my account

  Background:
    Given the store operates on a single channel in "United States"
    And there is a user "alice@setono.com" identified by "sylius"
    And I am logged in as "alice@setono.com"
    And the store has a gift card with code "GIFT-CARD-100" valued at "$100" associated to customer "alice@setono.com"
    And the store has a gift card with code "GIFT-CARD-200" valued at "$200" associated to customer "alice@setono.com"
    And the store has a gift card with code "GIFT-CARD-200-2" valued at "$200"
    And the store has a gift card with code "GIFT-CARD-200-3" valued at "$200" associated to customer "alices-husband@setono.com"

  @api
  Scenario: Browsing gift cards
    When I browse gift cards
    Then Gift cards list should contain a gift card with code "GIFT-CARD-100"
    And Gift cards list should contain a gift card with code "GIFT-CARD-200"
    And Gift cards list should not contain a gift card with code "GIFT-CARD-200-2"
    And Gift cards list should not contain a gift card with code "GIFT-CARD-200-3"
