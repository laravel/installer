Welcome to NukaCode Installer
================================
This is a modified version of the laravel installer. It allows you to install a fully configured version of the NukaCode Framework.

=======
Badges
=======
.. image:: https://scrutinizer-ci.com/g/NukaCode/installer/badges/quality-score.png?b=master
    :target: https://scrutinizer-ci.com/g/NukaCode/installer/?branch=master
.. image:: https://poser.pugx.org/nukacode/installer/v/stable.svg
    :target: https://packagist.org/packages/nukacode/installer
.. image:: https://poser.pugx.org/nukacode/installer/downloads.svg
    :target: https://packagist.org/packages/nukacode/installer
.. image:: https://poser.pugx.org/nukacode/installer/license.svg
    :target: https://packagist.org/packages/nukacode/installer
=====

Links
------
* `GitHub <https://github.com/NukaCode/installer>`_
* `Packagist <https://packagist.org/packages/nukacode/installer>`_


Install
-------
Run the following composer command to install the nukacode installer globally.

.. code::

    composer global require "nukacode/installer=~2.0"

Make sure to place the ``~/.composer/vendor/bin`` directory in your PATH so the laravel executable can be located by your system.

.. code::

    echo 'export PATH="$HOME/vendor/bin:$PATH' >> ~/.bash_profile
    source ~/.bash_profile

Usage
-----
Once installed, the simple laravel new command will create a fresh Laravel installation in the directory you specify.
For instance, laravel new blog would create a directory named blog containing a fresh Laravel installation with all dependencies installed.
This method of installation is much faster than installing via Composer:

.. code::

    laravel new blog

Slim Build
~~~~~~~~~~
By adding the ``--slim`` flag you can install a minimal version of the NukaCode Framework. This only includes Laravel base and core.

.. code::

    laravel new blog --slim

Build Cache
-----------
Each time the installer is run it checks the NukaCode build server to see if there is a newer version of the build to download.
If you have the latest build then it will use your local copy instead of downloading it again.

You can force the installer to download a new copy by adding the ``--force`` flag.

.. code::

    laravel new blog --force