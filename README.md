# PHP Network Weathermap 1.0 - Beta 2

This is PHP Network Weathermap fork, version 1.0.  The PHP Weathermap was
originally by Howard Jones (howie@thingy.com).  As Howie has scaled back his
Cacti plugin development work, we will release this version that incorporates
his core framework from the 0.98 version.  In future releases, we will look
to incorporate subequent releases of the WeatherMap core.

See the docs sub-directory for full HTML documentation, FAQ and example config.

See CHANGELOG.md for the most recent updates, listed by version.

See COPYING for the license under which php-weathermap is released.

## Compatability

This version only works with Cacti 1.2.x onwards.  A re-write of the user interface
is being made to make it more compatible with 1.2.x and 1.3.x releases.

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for downloads, change
logs, release notes and more!

## Community forums

Given the large scope of Cacti, the forums tend to generate a respectable amount
of traffic. Doing your part in answering basic questions goes a long way since
we cannot be everywhere at once. Contribute to the Cacti community by
participating on the [Cacti Community Forums](http://forums.cacti.net).

For Network Weathermap's core support, there is much more information along with
tutorials and updates available at Howard Jone's site:

    http://www.network-weathermap.com/

## Important Notes
This version of Weathermap only works with Cacti 1.2.x and is now ready for
beta, yet non-production use at this time.

**This version is NOT production ready!**

The location of backgrounds and object images has changed!  The upgrade script 
will attempt to move these backgrounds and images to the new locations, 
but you may have some cleanup to do especially if you customized the locations.

The directories: `cacti-resources` and `editor-resources` have been removed
in favor of the standard `js` and `css` folders.  You should remove these
folders after installation.

Since the Editor has essentially no security, this version will be 100% dependent
on the Cacti Security model to authorize users.

The Overlib library dependency has been removed in this release.

When reviewing the plugin in detail, there were so many possible enhancements
that could be incorporated into the tool, but for now, it's really just to
bring the Weathermap plugin fully into the Cacti 1.x and beyond.

## GitHub Documentation

Get involved in creating and editing Cacti Documentation!  Fork, change and
submit a pull request to help improve the documentation on
[GitHub](https://github.com/cacti/documentation).

## GitHub Development

Get involved in development of Cacti! Join the developers and community on
[GitHub](https://github.com/cacti)!

## Original Weathermap Plugin

Howard Jones original work can still be found on GitHub at the following location.

https://github.com/howardjones/network-weathermap

Howie has done extensive rework of his Weathermap API that we will look to incorporate
in future releases of the Cacti version of the plugin.

## Included 3rd Party Component Software

* ddSlick - A forked and jQueryUI compatible version of the jquery images dropdown
  plugin.

  See: https://jquery-plugins.net/ddslick-dropdown-with-images

* Network-Icons-SVG - A collection of network icons in SVG format converted
  to work with Weathermaps PNG format.

  See: https://github.com/aci686/Network-Icons-SVG

* The Bitstream Vera Open Source fonts (Vera\*.ttf) are copyright Bitstream, Inc.

  See: http://www.bitstream.com/font_rendering/products/dev_fonts/vera.html

* The manual uses the Kube CSS Framework and ParaType's PT Sans font.

  See: http://imperavi.com/kube/
  See: http://www.fontsquirrel.com/fonts/PT-Sans

* Some of the icons used in the editor, and also supplied in the images/ folder are
  from the excellent Fam Fam Fam Silk Icon collection by Mark James released under
  a Creative Commons license.

  See: http://www.famfamfam.com/lab/icons/silk/.
  See: http://creativecommons.org/licenses/by/2.5/

-----------------------------------------------------------------------------
Copyright (c) 2004-2023 - The Cacti Group, Inc.
