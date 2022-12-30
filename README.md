# PHP Network Weathermap 1.0 - Alpha

This is PHP Network Weathermap fork, version 1.0 originally by Howard Jones (howie@thingy.com)

See the docs sub-directory for full HTML documentation, FAQ and example config.

See CHANGELOG.md for the most recent updates, listed by version.

See COPYING for the license under which php-weathermap is released.

There is much more information, tutorials and updates available at:
    http://www.network-weathermap.com/

------

## Important Notes
This version of Weathermap only works with Cacti 1.x.x and is currently under
heavy development.

**This version is NOT production ready!**

The location of backgrounds and object images has changed!  The upgrade script 
(once written) will attempt to move these backgrounds and images to the new locations, 
but you may have some cleanup to do especially if you customized the locations.

The directories: `cacti-resources` and `editor-resources` have been removed
in favor of the standard `js` and `css` folders.

Since the Editor has essentially no security, this version will be 100% dependent
on the Cacti Security model to authorize users.

The Overlib library dependency has been removed in this release.

------

## PHP Weathermap contains components from other software developers:

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
