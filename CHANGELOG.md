Changelog
=========

## 0.6.0

### Added
- Customer relation on gift card
- API endpoints for gift card resource

## 0.5.0

- Fixture `setono_gift_card` now extended from `AbstractResourceFixture`,
  so use something like
  
  ```yaml
    setono_gift_card:
        options:
            random: 20
  ```
  
  rather than
  
  ```yaml
    setono_gift_card:
        options:
            amount: 20
  ```
  
  to get random 20 gift cards.
