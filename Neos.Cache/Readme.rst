--------------------
Neos Cache Framework
--------------------

.. note:: This repository is a **read-only subsplit** of a package that is part of the
Flow framework (learn more on `http://flow.neos.io <http://flow.neos.io/>`_).

If you want to use the Flow framework, please have a look at the `Flow documentation
<http://flowframework.readthedocs.org/en/stable/>`_

.. note:: The Neos Cache Framework now also supports `PSR-6<http://www.php-fig.org/psr/psr-6>`_
and `PSR-16<http://www.php-fig.org/psr/psr-6>`_ caches but those are not integrated in
Flow yet, so when you use them it's your responsibility to flush them appropriately as
``./flow flow:cache:flush`` will not do that in this case.
For both a simple factory is provided that can use similar configuration as the Flow
Framework cache configuration for backends.

Contribute
----------

If you want to contribute to the Flow framework, please have a look at
https://github.com/neos/flow-development-collection - it is the repository
used for development and all pull requests should go into it.
