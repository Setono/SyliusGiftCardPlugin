@managing_gift_card_configurations
Feature: Deleting an existing gift card configuration
  In order to remove a gift card configuration
  As an Administrator
  I want to delete an existing gift card configuration

  Background:
    Given the store operates on a single channel in "United States"
    And I am logged in as an administrator
    And the store has a gift card configuration with code "default_configuration"

  @api
  Scenario: Deleting an existing gift card configuration
    When I delete gift card configuration "default_configuration"
    Then I should be notified that it has been successfully deleted
    And I should not see a gift card configuration with code "default_configuration"
