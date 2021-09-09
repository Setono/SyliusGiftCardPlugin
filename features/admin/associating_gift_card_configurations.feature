@managing_gift_card_configurations
Feature: Associating an existing gift card configuration to a channel
  In order to define a different gift card configuration per channel
  As an Administrator
  I want to associate an existing gift card configuration to a channel with a locale

  Background:
    Given the store operates on a channel named "United States" in "USD" currency
    And that channel allows to shop using "English" and "French" locales
    And the store operates on a channel named "Europe" in "EUR" currency
    And that channel allows to shop using "English" and "French" locales
    And I am logged in as an administrator
    And the store has a gift card configuration with code "default_configuration"

  @api @stipe
  Scenario: Association an existing gift card configuration to a channel
    When I associate gift card configuration default_configuration to channel "Europe" and locale fr
    Then I should be notified that it has been successfully updated
    And I should see a gift card configuration with code "default_configuration"
    And It should have channel configuration for channel "Europe" and locale fr
