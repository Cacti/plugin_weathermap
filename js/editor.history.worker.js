/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';
// One owner per tab history, including on HTTP where Web Locks is unavailable.
// Only opaque browser IDs live here; map snapshots remain on the server.
var owners = new Map();
self.onconnect = function(event) {
	var port = event.ports[0], owned;
	port.onmessage = function(message) {
		if (message.data.release) {
			if (owned && owners.get(owned) === port) owners.delete(owned);
			port.close();
			return;
		}
		var key = message.data.key;
		if (typeof key !== 'string' || owned) return;
		var granted = !owners.has(key);
		if (granted) { owners.set(key, port); owned = key; }
		port.postMessage({granted: granted});
	};
	port.start();
};
