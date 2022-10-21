# ossearch_simple

Drupal 8 module for use with Smithsonian Open Source Search


## Usage
- Clone the repository or download the source
- Copy the files to your Drupal website, i.e. create the directory /sites/all/modules/custom/ossearch_simple and copy the files from the root of this repository into that directory.
- Enable the module.
- Use the block admin to add one or more custom searches to pages

### Using Composer
Since this module isn't published as a package to a repository, using composer to install the module necessitates modifying the root composer.json file for the site.

Option 1 is to add the module as a package by adding an entry to `repositories:[]` (see below) and running `composer require drupal/ossearch_simple`:
```
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        {
            "type": "package",
            "package": {
                "name": "drupal/ossearch_simple",
                "version": "1.0.0",
                "type": "drupal-custom-module",
                "source": {
                    "url": "https://github.com/couloir007/ossearch_simple.git",
                    "type": "git",
                    "reference": "master"
                }
            }
        }
    ],
```

Option 2 is to install by adding the location of the module as an additional repository and downloading the module to that location, e.g. 2nd entry here:

```
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.drupal.org/8"
        },
        {
            "type": "path",
            "url": "/path/to/modulefiles/ossearch_simple"
        }
    ],
```

## For more information about which Open Source Search server and search collection to use, please contact the OS Search support team. See Confluence.
