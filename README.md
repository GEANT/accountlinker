# AccountLinking module

This is the GÉANT [SimpleSAMLphp](https://github.com/simplesamlphp/simplesamlphp) module for linking accounts.

## Summary

![screen shot 2016-12-01 at 10 59 25](https://cloud.githubusercontent.com/assets/3790903/20789960/a1be1d68-b7b6-11e6-843c-9f53bc96f44f.png)

User accounts are created automatically by signing in using one of the many Identity Providers (universities, social media, guest provides, etc).

An 'account linking' module would link multiple accounts together, so that users will "be the same", regardless of whether they are logged in via their university, Twitter, facebook, Linkedin or whatever account.

#Manual account linking

Provides everything but an interface to do the actual account linking. This will have to be done manually by changing the values in the 'accounts' table.

![screen shot 2016-12-01 at 11 09 42](https://cloud.githubusercontent.com/assets/3790903/20789970/afd39e64-b7b6-11e6-91ed-20d52eeac59d.png)

User_id = TAL_id = is a group of account_ids



