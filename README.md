# PHP Network Weathermap 2.0

PHP Network Weathermap is a Cacti plugin for creating network maps that show
traffic utilisation and other monitored data. This Cacti-maintained fork builds
on the original PHP Network Weathermap by Howard Jones (howie@thingy.com), based
on his 0.98 series framework.

Version 2.0 adds a redesigned map editor, editable notes, curved link comments,
traffic colour presets, optional high-load link pulsing, separate icon and
background libraries, sharper hover graphs and two-step Undo and Redo.

See [the 2.0 manual](docs/index.html) for installation, upgrades, editor workflows,
image libraries, troubleshooting and the configuration/data-source reference.
[The editor workflow guide](docs/editor-workflow.md) links to the detailed manual.

See [CHANGELOG.md](CHANGELOG.md) for updates listed by version, and
[COPYING](COPYING) for the license.

## Bundled node icons

The node icon library in `images/objects/` includes modern corporate building,
branch office, hospital, clinic, data centre, warehouse and wireless access-point
(`AP.png`) icons, plus NAS (`NAS.png`), server (`Server.png`), printer
(`Printer.png`), CCTV recorder (`CCTV-NVR.png`), UPS (`UPS.png`) and CCTV camera
(`CCTV-Camera.png`) icons. These transparent PNGs are 96 × 96 pixels and can be selected from the editor's node
icon picker without uploading additional files.


## Compatibility

The plugin requires Cacti 1.2.29 or later, as specified in [INFO](INFO), and uses
Cacti's authentication and permissions. The editor includes theme handling for
Cacti 1.2.x and the developing 1.3.x series.

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for downloads, change
logs, release notes and more!

## Community forums

Given the large scope of Cacti, the forums tend to generate a respectable amount
of traffic. Doing your part in answering basic questions goes a long way since
we cannot be everywhere at once. Contribute to the Cacti community by
participating on the [Cacti Community Forums](http://forums.cacti.net).

For Network Weathermap's core support, there is much more information along with
tutorials and updates available at Howard Jones's site:

    http://www.network-weathermap.com/

## Important Notes

Back up the plugin and your map configurations before upgrading.

Backgrounds and node icons use separate directories: `images/backgrounds/` and
`images/objects/`. When upgrading from older versions, the upgrade script
attempts to move images and update their configuration paths. Check customised
image locations after upgrading, and ensure the poller account has permission
to move the relevant files and update map configurations.

The old `cacti-resources` and `editor-resources` directories have been replaced
by `js` and `css`. They are no longer used. The Overlib library dependency has
also been removed.

Editor access depends on Cacti's authentication and permissions. Review those
permissions when upgrading from older installations.

## GitHub Documentation

Get involved in creating and editing Cacti Documentation!  Fork, change and
submit a pull request to help improve the documentation on
[GitHub](https://github.com/cacti/documentation).

## GitHub Development

Get involved in development of Cacti! Join the developers and community on
[GitHub](https://github.com/cacti)!

## Original Weathermap Plugin

Howard Jones's original work can still be found on GitHub at the following location.

https://github.com/howardjones/network-weathermap

This fork is maintained separately from that upstream project.

## Included 3rd Party Component Software

* ddSlick - A forked and jQueryUI compatible version of the jquery images dropdown
  plugin.

  See: https://jquery-plugins.net/ddslick-dropdown-with-images

* Network-Icons-SVG - A collection of network icons in SVG format converted
  to work with Weathermaps PNG format.

  See: https://github.com/aci686/Network-Icons-SVG

* The Bitstream Vera Open Source fonts (Vera\*.ttf) are copyright Bitstream, Inc.

  See: http://www.bitstream.com/font_rendering/products/dev_fonts/vera.html

* Historical documentation assets include the Kube CSS Framework and ParaType's
  PT Sans font. The current manual uses a self-contained stylesheet and system fonts.

  See: http://imperavi.com/kube/
  See: http://www.fontsquirrel.com/fonts/PT-Sans

* Some of the icons used in the editor, and also supplied in the images/ folder are
  from the excellent Fam Fam Fam Silk Icon collection by Mark James released under
  a Creative Commons license.

  See: http://www.famfamfam.com/lab/icons/silk/.
  See: http://creativecommons.org/licenses/by/2.5/

-----------------------------------------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
