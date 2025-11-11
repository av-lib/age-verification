Summary:  Verify visitors age (ID/face scan/etc) in geographical regions that require it with multiple providers.

# Age Verification Library for PHP

This project includes a set of PHP scripts that may be useful for PHP websites integrating age verification checks.
It may be useful to sites that contain adult content that are now potentially required to verify user age under state law.
It is intended to be primarily a *server-side* solution as opposed to a client-side solution.

While it is still a fair bit of effort to integrate age verification into a website, the hope with this library is that
it may be faster than starting from scratch.  

This library is flexible, you can override any functions you need to.  You can add your own custom providers, or even 
use it with no providers and only use the region-blocking portion of it.

## Supported Providers

| Provider                           | ID Scan (full) | ID Scan (redacted) | Facial Age Estimation | Credit Card                 | E-mail | Cost                  |
|------------------------------------|----------------|--------------------|-----------------------|-----------------------------|--------|-----------------------|
| [Redact-ID](https://redact-id.com) | ✅             | ✅                 |                       |                             |        | $20/mo+ ($0.05/verif) |
| [Go.Cam](https://go.cam)           | ✅             |                    | ✅                    | ✅ (But not FR, DE, US, GB) | ✅     | Free                  |

You may send a pull request for your preferred provider if it fits within the existing technical design of this library 
and can be licensed under MIT.  If it is large or requires unusual dependencies, it could be added via a separate repo
via a plugin architecture.

## Features

- Validation of user age through multiple providers.
- Your account system does not need to be aware of the particulars of specific provider's logic, so you can change
  providers or add providers easily later.
- Extensible, so you can configure other methods that trigger the validation (ex: flag as 18+ after your own credit card
  checks, etc.)
- Support for storing age verification status both for registered users (with account) and guest users (without account).
  Guest user validation is remembered via a server-verifiable cookie.
- Can skip age checks for search engine bot traffic so your site does not have trouble with search engines.
- Can perform age checks in only specific regions that require it, or everywhere, or whatever your preference is.  You
  choose the territories.  Defaults based on US states that require age verification for 18+ content.
- Cron script to download GeoLite database, test it, and install it automatically.  It will also trigger an alert 
  if the database has gone stale.
- Memcache support to reduce unnecessary database access.

## Requirements

- Tested on PHP 8.3.6 on Linux, may work in other environments also.
- Tested on MySQL server 8 for database storage.
- PHP extensions for MySQL PDO and memcache.
- Memcache server for memory caching

## Instructions for Use

1. Register with one or multiple of the supported providers.

2. Create your own implementation class of AgeVerification, using XXX as an example.  The library is designed to be 
subclassed, so if a particular method does not work for your site, you can override it as needed.  For instance, 
   - It is suggested you check which territories you are required to verify age and override that function if it is not the 
same as the main class provides for you.
   - Provide PDO & Memcache access by overriding the relevant function.

3. Database:
In your accounts table, create new fields for an `ageVerified` enum and `redactIdReference`:

```sql
ALTER TABLE `accounts` ADD `ageVerified` ENUM('REDACT-ID','GOCAM','COOKIE') NULL;
ALTER TABLE `accounts` ADD `verificationReference` VARCHAR(255) NULL AFTER `ageVerified`;
```

Having a not-null value for `ageVerified` will flag the account as having undergone age verification.  ID Providers can
optionally store some data into the `verificationReference` field, for instance, a hash to show that verification
was performed.

4. Also create a new table to store ageTokens.  These are used by guest users to remember that they have gone 
through age verification:

```sql
-- Table Structure
CREATE TABLE `ageTokens` (
  `tokenID` bigint NOT NULL,
  `token` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `issued` datetime NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '0'
);

-- Indexes
ALTER TABLE `ageTokens`
  ADD PRIMARY KEY (`tokenID`),
  ADD UNIQUE KEY `token_index` (`token`) USING BTREE;

-- Auto Increment
ALTER TABLE `ageTokens`
  MODIFY `tokenID` bigint NOT NULL AUTO_INCREMENT;
COMMIT;
```

5. Create an ageBlock.php page that users are re-directed to when they are to be blocked due to age verification 
requirement.  See XXX for example.  It should check if the user is blocked, and if so, include links for each provider 
you are using.  This page will also serve as the point for receiving callback events.  It does not have to be named
ageBlock.php, you can name it something else as long as you update the relevant function.

6. On every page that a user should be age checked, call the `redirectToAgeVerificationIfShould()` function.  This should
happen before page output is done, but can happen after session is started.

```php
AgeVerificationMySite::instance()->redirectToAgeVerificationIfShould();
```

7. Setup the geographical database `downloadTestAndInstall.php` database script:
   - You can configure it to use the official download location or a github mirror, depending on your preferences.
   - Modify the script to point to your own Health Checks URL (or comment it out if you don't want alerts, but that is 
   not recommended).
   - Set up a cron to run the GeoLite every other day.
   - Run the script once so the database will be available.

8. A user might choose to age verify as a guest *before* they register.  Thus, you should upgrade the account to age
	verified status at certain key points if they have a valid guest age verification token.  These points are likely
	check points:
	- Login via login form
	- Login via cookie
	- Registration

Example:
```php
// If account was not previouly age verified, but has the age verification guest token, set their
// account appropriately.
if (AgeVerificationMySite::instance()->getAgeVerifiedAccount($accountID) == false &&
	isset($_COOKIE["ageVerificationToken"]) && 
	AgeVerificationMySite::instance()->checkAgeVerificationToken($_COOKIE["ageVerificationToken"]) === true)
{
	AgeVerificationMySite::instance()->setAgeVerifiedAccount($accountID, "COOKIE"); // Upgrades account to age verified status
}
```

9. Test thoroughly.  You can force age verification checking on your localhost testing by overriding 
	`ipInRestrictedTerritory()` to always return true.  You can simulate a successful verification by uncommenting
	the `test-force` in the example `ageBlock.php` page.  Remember that to reset after a test, you need to change the
	database account fields back, as well as flush memcache, as well as delete the relevant cookies.  
		

