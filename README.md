# Entity Clone CLI

The interface to manipulate data of Entity Clone package: https://github.com/danilocgsilva/entity-clone

## Tests and environment

The environment already awaits to have the properly environment variables to connect to database. The tests is designed to work with real database. So provide the following variables to the environment:
* APP_ENV: you want to set at as 'dev' for development. Empty value already set it to 'dev'. For test, set it as 'test', considering the `phunit.xml`
* DB_NAME: might the named as 'entity_clone', or whatever you want
* DB_NAME_TEST: might the named as 'entity_clone_cli', or whatever you want
* DB_HOST: the host of your database
* DB_USER: the user of your database
* DB_PASSWORD: the password for your user database
