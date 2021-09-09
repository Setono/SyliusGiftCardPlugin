@managing_gift_card_configurations
Feature: Adding a new gift card configuration
  In order to create a gift card configuration
  As an Administrator
  I want to add a new gift card configuration

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator

  @api
  Scenario: Adding gift card configuration
    When I want to create a new gift card configuration
    And I specify its code as "new_configuration"
    And I specify it as default configuration
    And I add it
    Then I should be notified that it has been successfully created
    And I should see a gift card configuration with code "new_configuration"
    And It should be enabled
    And It should be default configuration

  @api
  Scenario: Adding disabled gift card configuration
    When I want to create a new gift card configuration
    And I specify its code as "new_configuration"
    And I disable it
    And I add it
    Then I should be notified that it has been successfully created
    And I should see a gift card configuration with code "new_configuration"
    And It should not be enabled
    And It should not be default configuration
