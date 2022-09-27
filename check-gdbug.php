<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

	ob_start();

	if(isset($argv))
	{
	      $env = "CLI";
	}
	else
	{
	      $env = "WEB";
	}

	print wordwrap("Some version of the GD graphics library have a bug in their handling of Alpha channels. Unfortunately, Weathermap uses these to draw Nodes.");
	print ($env=='CLI'?"\n\n":"\n<p>");

	print wordwrap("This program will test if your PHP installation is using a buggy GD library.");
	print ($env=='CLI'?"\n\n":"\n<p>");

	print wordwrap("If you are, you should either use PHP's built-in (aka 'bundled') GD library, or update to GD Version 2.0.34 or newer. Weathermap REQUIRES working Alpha support.");
	print ($env=='CLI'?"\n\n":"\n<p>");

	print wordwrap("Let's see if you have the GD transparency bug...");
	print ($env=='CLI'?"\n\n":"\n<p>");
	print wordwrap("If you see no more output, or a segfault, then you do, and you'll need to upgrade.");
	print ($env=='CLI'?"\n\n":"\n<p>");
	print wordwrap("If you get other errors, like 'undefined function', then run check.php to\nmake sure that your PHP installation is otherwise OK.");
		     print ($env=='CLI'?"\n\n":"\n<p>");
	print "Here we go...";
	print ($env=='CLI'?"\n\n":"\n<p>");

	// make sure even the affected folks can see the explanation
	ob_flush();
	flush();

	$temp_width = 10;
	$temp_height = 10;

	$node_im=imagecreatetruecolor($temp_width,$temp_height );
	imageSaveAlpha($node_im, TRUE);
	$nothing=imagecolorallocatealpha($node_im,128,0,0,127);
	imagefill($node_im, 0, 0, $nothing);
	imagedestroy($node_im);

	print "...nope. We got past the risky part, so that's good.\nYour GD library looks healthy.\n";
	print ($env=='CLI'?"\n":"\n<p>");
