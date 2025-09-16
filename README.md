Commerce Wise
===============

CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Configuration

INTRODUCTION
------------
This module integrates Drupal Commerce with Wise Quick Pay,
https://wise.com/p/business/quickpay


## Features

* Offsite payment gateway in Drupal Commerce core.
* Webhook integration for real time updates.
* Payments in Drupal Commerce synchronized with Wise.com.


REQUIREMENTS
------------
This module should be added to your codebase via Composer

`composer require "drupal/commerce_wise:^1.0"`

You must also have a Wise business account to configure for your integration.

There are no other requirements than [Commerce Core 3](https://www.drupal.org/project/commerce)

You can sign up for one [here](https://wise.com/invite/dic/valentinom6).


CONFIGURATION
-------------

## Payment gateway configuration
Once you've installed the module, you must navigate to the Drupal Commerce
payment gateway configuration screen to define a payment gateway configuration.
This will require providing a Wise public key, Wise tag of Wise.com business account and
configuring the mode (Live vs. Test).

### Webhook setup
* Log into you Wise Business account.
* Go to `Integration & Tools => Webhooks`
* Create new Webhook of type `Account deposit events`
  * Enter any name
  * Under URL enter `https://yourwebsite.com/payment/notify/machine_name_payment_gateway`
    * Replace `machine_name_payment_gateway` with machine name of payment gateway which you created in Drupal

### Public key
* Go to https://docs.wise.com/api-docs/webhooks-notifications/event-handling#requests
* Copy either sandbox or production key, depending on how the payment gateway mode is configured.

### Obtaining Wise tag.
* Log into you Wise Business account.
* Go to `Security & Privacy => Contacts discoverability `
* If there is no tag yet, create new one.





