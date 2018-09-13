@managing_gift_cards
Feature: Adding a new gift cards
    In order to extend my merchandise
    As an Administrator
    I want to add a new gift card to the shop

    Background:
        Given the store operates on a single channel in "United States"
        And the store has "Standard" shipping category
        And I am logged in as an administrator

    @ui
    Scenario: Adding a new gift card
        Given I want to create a new gift card
        When I specify its code as "gift_card_100"
        And I name it "Gift card 100" in "English (United States)"
        And I set its slug to "dice-brewing" in "English (United States)"
        And I set its price to "$100.00" for "United States" channel
        And I add it
        Then I should be notified that it has been successfully created
        And the product "Gift card 100" should appear in the store
        And the product "Gift card 100" should be a gift card
