@managing_gift_card_configurations
Feature: Updating an existing gift card configuration
  In order to modify a gift card configuration
  As an Administrator
  I want to edit an existing gift card configuration

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator
    And the store has a gift card configuration with code "default_configuration"

  @api
  Scenario: Updating an existing gift card configuration
    When I want to update gift card configuration "default_configuration"
    And I disable it
    And I save my changes
    Then I should be notified that it has been successfully updated
    And I should see a gift card configuration with code "default_configuration"
    And It should be disabled

  @api
  Scenario: Updating an existing gift card configuration
    When I want to update gift card configuration "default_configuration"
    And I enable it
    And I save my changes
    Then I should be notified that it has been successfully updated
    And I should see a gift card configuration with code "default_configuration"
    And It should be enabled
