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
