@applying_gift_card
Feature: Applying gift card
    In order to pay proper amount after using the gift card
    As a Customer
    I want to have gift card applied to my cart

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt" priced at "$100.00"
        And the store has a product "Gift card 50" priced at "$50.00"
        And this product is a gift card
        And the store has gift card "Gift card 50" with code "JKFEFKDFK"
        And the store has a product "Gift card 200" priced at "$200.00"
        And this product is a gift card
        And the store has gift card "Gift card 200" with code "HVHBJBIJN"
        And the store ships everywhere for free
        And the store allows paying offline

    @ui @javascript
    Scenario: Applying gift card for my cart
        Given I am a logged in customer
        When I add product "PHP T-Shirt" to the cart
        And I use gift card with code "JKFEFKDFK"
        Then my cart total should be "$50.00"
        And my discount gift card should be "-$50.00"

    @ui
    Scenario: Placing an order with a gift card
        Given I am a logged in customer
        When I add product "PHP T-Shirt" to the cart
        And I place an order with a gift card code "JKFEFKDFK"
        Then The gift card with the code "JKFEFKDFK" should be inactive

    @ui
    Scenario: Placing an order using a part of the gift card
        Given I am a logged in customer
        When I add product "PHP T-Shirt" to the cart
        And I place an order with a gift card code "HVHBJBIJN" which covers the entire total order
        Then The gift card with the code "HVHBJBIJN" should be active and have value "$100.00"