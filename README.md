# woocommerceAtosPlugin

Wordpress plugin that enable Atos payment for Woocommerce. 

**This fork is compatible with WooCommerce >= 2.3**



## Atos folder structure
```
/var/www/atos
├── bin
│   ├── request
│   └── response
└── param
    ├── certif.fr.XXXXXXXXXXX
    ├── parmcom.XXXXXXXXXXX
    ├── parmcom.webaffaires
    └── pathfile
```
Where `XXXXXXXXXXX` is your merchant id provided by your bank.

### Bin

When requested, your bank must provide you a set of binary files, ie:

    request_2.6.9_3.4.2
    request_64b_1
    ...
    response_2.6.9_3.4.2
    response_64b_1

Depending on your web server architecture, you have to copy request/response binaries into the `bin/` folder. If you don't know which one of theses binaries is the correct one, follow this procedure

- Copy all binary file into the `bin/`
- Check the print shared library dependencies for EACH request binary, ie.
```    
$ ldd request_64b_1
```
- If the output say `not a dynamic executable` remove it, else you just find the right one. 
- Give executable permission for both binaries
```
$ chmod +x response request
```

### Param

#### pathfile

`F_DEFAULT!`, `F_PARAM!` and `F_CERTIFICATE!` must provide absolute path, ie.
```
F_DEFAULT!/var/www/atos/param/parmcom.webaffaires!
F_PARAM!/var/www/atos/param/parmcom!
F_CERTIFICATE!/var/www/atos/param/certif!
```
To use credit cards logos given with this plugin, change images path to
```
D_LOGO!/wp-content/plugins/woocommerceAtos/images/!
```

## Backoffice Wordpress

- Create a page that contains shortcode below and copy generated permalink
```
[woocommerce_atos_automatic_response]
```
- Activate "WooCommerceAtos" plugin and go to settings
- Fill parameters fields with your own values
- Paste your permalink into `automatic_response_url` field.
- Save



## Test mode

Use these values to test your installation.

Credit card success infos

    Credit card n°: 4974934125497800
    Crypt key: 600
    Expiration date: anything in the future