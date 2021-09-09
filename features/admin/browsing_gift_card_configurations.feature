@managing_gift_card_configurations
Feature: Browsing existing gift card configurations
  In order to manage the existing gift card configurations
  As an Administrator
  I want to browse existing gift card configurations

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator
    And the store has a gift card configuration with code "default_configuration"
    And the store has a gift card configuration with code "second_configuration"

  @api
  Scenario: Browsing gift cards
    When I browse gift card configurations
    Then I should see a gift card configuration with code "default_configuration"
    And I should see a gift card configuration with code "second_configuration"
