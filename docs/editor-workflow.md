# Editing nodes and links

For an ordinary interface traffic link, use the main controls to choose an
interface, set its maximum bandwidth, change the line width and add IN/OUT
comments. The current interface shows its graph title and interface description,
with the RRD path below it.

Search by device, interface or description, or choose **Browse all**. Use
**Next results** and **Previous results** to browse further pages without
replacing the current selection. Selecting
an entry does not overwrite the link until you click **Use interface**. This
sets its traffic source, click destination and hover graph together. Click
**Save** to persist the changes or **Cancel** to leave the map unchanged.
**Tidy** adjusts the link route; **Via** lets you place a routing point.

**Advanced settings** exposes raw targets, custom click destinations, multiple
hover graphs and the existing low-level editor controls. These are useful for
combining sources, nonstandard RRD targets or external graph images.

For a node used as an endpoint label or icon, edit the display name, icon and
position in the main controls. **Move** places it on the map; **Clone** duplicates
it. Advanced settings includes its internal name, custom click destination and
hover graphs, useful for device measurements such as CPU or memory. Choosing a
graph only enables **Add** and **Replace**; it does not apply it automatically.

Delete controls are separate from Save and Cancel. Confirmation identifies the
item by display name and explains what is removed. Deleting a node also removes
its connected map links. Cacti devices and graphs remain.


## Undo and Redo

Use **Undo** and **Redo** at the top right of the editor toolbar to reverse or
reapply the last two saved map changes. Each completed move, placement or saved
dialog counts as one action. Buttons are disabled when no matching action is
available; their tooltips identify the next action. Keyboard shortcuts are
**Ctrl/Cmd+Z** for Undo and **Ctrl/Cmd+Shift+Z** for Redo. Text fields retain their
normal text-editing shortcuts. Save or cancel an open dialog first.

History covers map configuration changes: nodes, connected links, notes, via
points, labels, icons, map properties, colours and raw object configuration.
Undoing a node deletion restores its connected map links too. A new change after
Undo clears Redo. No-op saves and failed saves do not consume a step.

History belongs to the current browser tab, map and authenticated session.
Duplicated tabs start a separate history; reloading the same tab keeps it.
Tab ownership uses Web Locks, with a SharedWorker fallback for HTTP installations.
If neither browser API is available, history stays disabled and normal editing
continues. Changes made elsewhere invalidate it rather than overwriting someone else's
work. Included configuration files and managed images are checked before a
restore. Uploading or deleting library files, deleting map files, Cacti devices,
graphs and personal editor preferences are outside this history.

Snapshots are stored privately outside the web root, not in browser data or
Cacti's concurrently written session rows. They expire after four hours of
inactivity. Storage retains up to eight recently changed maps/tabs and 8 MiB per
session; older histories are discarded when either limit is reached. Temporary
storage must be writable by the PHP service account. No database upgrade is
required.
