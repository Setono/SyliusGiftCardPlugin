@buying_gift_card
Feature: Buying a gift card
    In order to buy a gift card
    As a Customer
    I want to be able to buy a gift card

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Gift card 100" priced at "$100.00"
        And this product is a gift card
        And the store ships everywhere for free
        And the store allows paying offline

    @ui
    Scenario: Buying a gift card
        Given I am a logged in customer
        And I have product "Gift card 100" in the cart
        When I proceed selecting "Offline" payment method
        And I confirm my order and pay successfully
        Then I should see the thank you page
        And I should be notified that email with gift card code
