Coinco-magento
================

Accept Bitcoin on your Magento-powered website with Coinco.

Download the plugin here:
https://github.com/CoinApex/coinco-magento/archive/master.zip

Installation
-------

Download the plugin and type in your command line:
```
mv <downloaded_coinco_magento> <magento_root_dir>/app/code/community/Coinco
```

After installation, open Magento Admin and navigate to `System > Configuration > Payment Methods`

Custom events
-------

The plugin sends two events - 'coinbase_callback_received' when a callback is
received, and 'coinbase_order_cancelled' when an order is cancelled. You can
use these events to implement custom functionality on your Magento store.
