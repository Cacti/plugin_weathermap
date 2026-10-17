<?php
/*
 * Copyright (C) 2004-2026 The Cacti Group.
 * Licensed under the GNU General Public License, version 2 or later.
 */

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EditorHistoryTest extends TestCase {
	private string $directory;
	private string $file;
	private string $cwd;

	protected function setUp(): void {
		$this->cwd = getcwd();
		chdir(dirname(__DIR__, 2));
		require_once 'setup.php';
		require_once 'lib/WeatherMap.class.php';
		require_once 'lib/editor.inc.php';
		require_once 'lib/editor.actions.php';
		$this->directory = sys_get_temp_dir() . '/wm-history-' . bin2hex(random_bytes(6));
		mkdir($this->directory);
		$this->file = $this->directory . '/map.conf';
		file_put_contents($this->file, "# preserve original formatting\nWIDTH 600\nHEIGHT 300\nNODE a\n LABEL A\n POSITION 40 50\nNODE b\n LABEL B\n POSITION 200 100\nLINK a-b\n NODES a b\n BANDWIDTH 100M\n VIA 100 70\n");
		session_start();
		$_SESSION = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['__test_nfilter_request'] = [];
	}

	protected function tearDown(): void {
		foreach (glob($this->directory . '/{*,.*}', GLOB_BRACE) as $file) if (is_file($file) || is_link($file)) unlink($file);
		rmdir($this->directory);
		$history = WeathermapEditorHistory\storagePath();
		if (is_file($history) || is_link($history)) unlink($history);
		session_destroy();
		chdir($this->cwd);
	}

	private function map(): WeatherMap {
		$map = new WeatherMap;
		$map->context = 'editor';
		self::assertTrue($map->ReadConfig($this->file));
		return $map;
	}

	private function request(string $action, array $extra = []): void {
		$map = $this->map();
		$GLOBALS['__test_request'] = array_merge([
			'action' => $action, 'history_id' => str_repeat('a', 32),
			'config_revision' => WeathermapConfig\snapshotRevision($map->config_revision, $map->config_dependencies, $map->config_root_identity)
		], $extra);
		http_response_code(200);
	}

	private function history(string $action): array {
		$this->request($action);
		ob_start();
		try { WeathermapEditorHistory\handle($this->file, $action); return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR); }
		finally { ob_end_clean(); }
	}

	private function move(int $x): string {
		$this->request('move_node', ['node_name' => 'a', 'x' => $x, 'y' => 50]);
		moveNode($this->file, 0);
		self::assertSame(200, http_response_code());
		return file_get_contents($this->file);
	}

	public function testTwoStepsBackAndForwardRestoreExactBytesIncludingViaChanges(): void {
		$this->move(50);
		$first = file_get_contents($this->file);
		$second = $this->move(70);
		$third = $this->move(90);
		self::assertSame(2, $this->history('history_status')['undo']);
		self::assertSame(1, $this->history('undo')['undo']);
		self::assertSame($second, file_get_contents($this->file));
		self::assertSame(2, $this->history('undo')['redo']);
		self::assertSame($first, file_get_contents($this->file));
		self::assertArrayHasKey('error', $this->history('undo'));
		self::assertSame(409, http_response_code());
		$this->history('redo'); self::assertSame($second, file_get_contents($this->file));
		$this->history('redo'); self::assertSame($third, file_get_contents($this->file));
		self::assertSame(0, $this->history('history_status')['redo']);
	}

	public function testDeletingNodeRestoresItsConnectedLinkAndOriginalFormatting(): void {
		$original = file_get_contents($this->file);
		$this->request('delete_node', ['param' => 'a']);
		deleteNode($this->file);
		self::assertArrayNotHasKey('a', $this->map()->nodes);
		self::assertArrayNotHasKey('a-b', $this->map()->links);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
		self::assertArrayHasKey('a-b', $this->map()->links);
		$this->history('redo');
		self::assertArrayNotHasKey('a-b', $this->map()->links);
	}

	public function testLinkCreationDeletionAndNotesUseSameHistory(): void {
		$this->request('delete_link', ['param' => 'a-b']); deleteLink($this->file);
		self::assertArrayNotHasKey('a-b', $this->map()->links);
		$this->history('undo'); self::assertArrayHasKey('a-b', $this->map()->links);
		$this->request('add_link2', ['param' => 'a', 'param2' => 'b']); addLink($this->file);
		self::assertArrayHasKey('a-ba', $this->map()->links);
		$this->history('undo'); self::assertArrayNotHasKey('a-ba', $this->map()->links);
		self::assertSame(1, $this->history('history_status')['redo']);
		$this->request('set_note_properties', ['note_name' => '', 'note_text' => '5G backup', 'note_font' => '3', 'note_colour' => '#0000ff', 'note_align' => 'left', 'note_x' => 100, 'note_y' => 100]);
		setNoteProperties($this->file, 0);
		self::assertCount(1, wm_editor_note_data($this->map()));
		self::assertSame(0, $this->history('history_status')['redo'], 'a new action clears redo');
		$this->history('undo'); self::assertCount(0, wm_editor_note_data($this->map()));
		$this->history('redo'); self::assertCount(1, wm_editor_note_data($this->map()));
	}

	public function testOtherTabsMapsAndExternalChangesCannotOverwriteCurrentConfiguration(): void {
		$this->move(60);
		$this->request('undo', ['history_id' => str_repeat('b', 32)]);
		ob_start(); WeathermapEditorHistory\handle($this->file, 'undo'); ob_end_clean();
		self::assertSame(409, http_response_code());
		$other = $this->directory . '/other.conf'; copy($this->file, $other);
		self::assertNotSame(WeathermapEditorHistory\key($other), WeathermapEditorHistory\key($this->file));
		$this->request('undo');
		file_put_contents($this->file, "\n# another editor saved\n", FILE_APPEND);
		$external = file_get_contents($this->file);
		ob_start(); WeathermapEditorHistory\handle($this->file, 'undo'); ob_end_clean();
		self::assertSame(409, http_response_code());
		self::assertSame($external, file_get_contents($this->file));
		self::assertSame(0, $this->history('history_status')['undo']);
	}

	public function testIncludeChangesAndDeletedMapsAreProtected(): void {
		$include = $this->directory . '/shared.conf';
		file_put_contents($include, "NODE template\n LABEL shared\n");
		file_put_contents($this->file, "INCLUDE $include\n" . file_get_contents($this->file));
		$this->move(60);
		file_put_contents($include, "# changed\n", FILE_APPEND);
		$expected = file_get_contents($this->file);
		self::assertArrayHasKey('error', $this->history('undo'));
		self::assertSame($expected, file_get_contents($this->file));
		$this->move(70);
		$this->request('undo'); unlink($this->file);
		ob_start(); WeathermapEditorHistory\handle($this->file, 'undo'); ob_end_clean();
		self::assertSame(404, http_response_code());
		self::assertFileDoesNotExist($this->file);
	}

	public function testFailedAndUnchangedSavesDoNotConsumeHistory(): void {
		$this->move(60);
		$this->move(60);
		self::assertSame(1, $this->history('history_status')['undo']);
		$this->request('move_node', ['node_name' => 'a', 'x' => 80, 'y' => 50, 'config_revision' => str_repeat('0', 64)]);
		moveNode($this->file, 0);
		self::assertSame(409, http_response_code());
		self::assertSame(1, $this->history('history_status')['undo']);
	}

	public function testReadOnlyHistoryStatusDoesNotExposeConfigAndGetCannotRestore(): void {
		$this->move(60);
		$status = $this->history('history_status');
		self::assertSame(['undo', 'redo', 'undoLabel', 'redoLabel'], array_keys($status));
		$this->request('undo'); $_SERVER['REQUEST_METHOD'] = 'GET';
		$before = file_get_contents($this->file);
		ob_start(); WeathermapEditorHistory\handle($this->file, 'undo'); ob_end_clean();
		self::assertSame(400, http_response_code());
		self::assertSame($before, file_get_contents($this->file));
	}
	public function testNormalPageRequestsCannotOverwritePrivateHistory(): void {
		$this->move(60);
		$first = file_get_contents($this->file);
		$this->move(80);
		// Model a late page/graph request writing an old Cacti session snapshot.
		$_SESSION = ['weathermap_editor_history' => []];
		$GLOBALS['weathermap_editor_history'] = [];
		$this->history('undo');
		self::assertSame($first, file_get_contents($this->file));
		self::assertSame(1, $this->history('history_status')['redo']);
		self::assertSame(0700, fileperms(dirname(WeathermapEditorHistory\storagePath())) & 0777);
		self::assertSame(0600, fileperms(WeathermapEditorHistory\storagePath()) & 0777);
	}

	public function testFailedAtomicRestorePreservesBothStacks(): void {
		$this->move(60);
		$before = file_get_contents($this->file);
		$this->request('undo');
		WeathermapEditorHistory\load();
		$key = WeathermapEditorHistory\key($this->file);
		$entry = $GLOBALS['weathermap_editor_history'][$key];
		$current = WeathermapEditorHistory\snapshot($this->map(), $this->file);
		$lock = WeathermapConfig\acquire($this->directory);
		try {
			// Another writer wins immediately before our atomic revision check.
			file_put_contents($this->file, "# concurrent change\n", FILE_APPEND);
			$step = end($entry['undo']);
			self::assertFalse(WeathermapEditorHistory\restore($this->file, $current, $step['snapshot'], $lock));
			self::assertSame($entry, $GLOBALS['weathermap_editor_history'][$key]);
			self::assertSame($before . "# concurrent change\n", file_get_contents($this->file));
		} finally { WeathermapConfig\release($lock); }
	}

	public function testRawConfigurationAndLegacyRequestsRemainSupported(): void {
		$this->request('set_node_config', ['node_name' => 'a', 'item_configtext' => "NODE a\n LABEL Changed\n POSITION 40 50\n"]);
		setNodeConfig($this->file);
		self::assertSame('Changed', $this->map()->nodes['a']->label);
		$this->history('undo'); self::assertSame('A', $this->map()->nodes['a']->label);
		$this->history('redo'); self::assertSame('Changed', $this->map()->nodes['a']->label);
		$this->request('move_node', ['history_id' => '', 'node_name' => 'a', 'x' => 90, 'y' => 50]);
		moveNode($this->file, 0);
		self::assertSame(90, (int) $this->map()->nodes['a']->x);
		self::assertSame(0, $this->history('history_status')['undo'], 'untracked legacy saves invalidate old history safely');
	}

	public function testMapColoursAndNewNodesCanBeUndone(): void {
		$before = file_get_contents($this->file);
		require_once 'lib/editor.colours.php';
		$data = wm_asset_colour_data($this->map());
		$data['outline'] = '#ff0000';
		$this->request('set_map_colours', ['colours_json' => json_encode($data)]);
		ob_start(); wm_editor_colours($this->file, 'set_map_colours'); $response = json_decode(ob_get_clean(), true);
		self::assertArrayNotHasKey('error', $response);
		self::assertNotSame($before, file_get_contents($this->file));
		$this->history('undo'); self::assertSame($before, file_get_contents($this->file));
		$this->request('add_node', ['x' => 300, 'y' => 100]); addNode($this->file, 0);
		self::assertCount(3, array_filter($this->map()->nodes, fn($node) => $node->x !== null));
		$this->history('undo'); self::assertSame($before, file_get_contents($this->file));
		$this->history('redo'); self::assertCount(3, array_filter($this->map()->nodes, fn($node) => $node->x !== null));
	}

	public function testAnIconDeletedAfterTheNodeWasDeletedIsNotRestoredAsBrokenReference(): void {
		// The library image is now absent; deleting its node must still be possible.
		$original = str_replace(' LABEL A', " ICON images/objects/removed-history-fixture.png\n LABEL A", file_get_contents($this->file));
		file_put_contents($this->file, $original);
		$this->request('delete_node', ['param' => 'a']); deleteNode($this->file);
		$deleted = file_get_contents($this->file);
		self::assertArrayHasKey('error', $this->history('undo'));
		self::assertSame($deleted, file_get_contents($this->file));
		self::assertSame(1, $this->history('history_status')['undo']);
	}

	public function testRetargetedRootAliasCannotUseOldHistory(): void {
		$original = $this->file;
		$alias = $this->directory . '/alias.conf'; symlink($original, $alias);
		$this->file = $alias;
		$this->move(60);
		$other = $this->directory . '/other.conf'; copy($original, $other);
		unlink($alias); symlink($other, $alias);
		$before = file_get_contents($other);
		self::assertArrayHasKey('error', $this->history('undo'));
		self::assertSame(409, http_response_code());
		self::assertSame($before, file_get_contents($other));
	}

	public function testMalformedOldHistoryDoesNotPreventSaving(): void {
		$this->move(60);
		file_put_contents(WeathermapEditorHistory\storagePath(), '{incomplete');
		$this->move(80);
		self::assertSame(1, $this->history('history_status')['undo']);
		$this->history('undo'); self::assertSame(60, (int) $this->map()->nodes['a']->x);
	}

	public function testUnavailableHistoryStorageStillAllowsNormalMapSaves(): void {
		$this->request('move_node', ['node_name' => 'a', 'x' => 85, 'y' => 50]);
		$history = WeathermapEditorHistory\storagePath();
		symlink($this->file, $history);
		try {
			moveNode($this->file, 0);
			self::assertSame(200, http_response_code());
			self::assertSame(85, (int) $this->map()->nodes['a']->x);
			self::assertTrue(is_link($history), 'untrusted history paths must not be followed or replaced');
		} finally { unlink($history); }
	}

	public function testSavingWithoutALoadedSnapshotDoesNotInventAnUndoState(): void {
		$this->move(60);
		$this->request('set_map_properties');
		$revision = hash_file('sha256', $this->file);
		$map = new WeatherMap;
		$map->context = 'editor';
		$map->title = 'Fresh configuration';
		self::assertTrue(WeathermapEditorHistory\write($map, $this->file, false, $revision));
		self::assertSame('Fresh configuration', $this->map()->title);
		self::assertSame(0, $this->history('history_status')['undo']);
	}

	public function testSavingAfterExpiryStartsANewHistoryInsteadOfRevivingOldSteps(): void {
		$this->move(60);
		$this->move(70);
		foreach ($GLOBALS['weathermap_editor_history'] as &$entry) $entry['time'] = time() - 14401;
		unset($entry);
		WeathermapEditorHistory\persist();
		$before = file_get_contents($this->file);
		$this->move(80);
		self::assertSame(1, $this->history('history_status')['undo']);
		$this->history('undo');
		self::assertSame($before, file_get_contents($this->file));
		self::assertSame(0, $this->history('history_status')['undo']);
	}

	public function testUndoPrunesAnOversizedRedoSnapshotBeforeReportingAvailableSteps(): void {
		$before = WeathermapEditorHistory\snapshot($this->map(), $this->file);
		// A valid large raw edit can fit on disk but exceed the encoded history budget.
		file_put_contents($this->file, file_get_contents($this->file) . str_repeat('#' . str_repeat('x', 1022) . "\n", 7168));
		$large = file_get_contents($this->file);
		$this->request('set_map_properties');
		$after = WeathermapEditorHistory\snapshot($this->map(), $this->file);
		WeathermapEditorHistory\load();
		WeathermapEditorHistory\remember(WeathermapEditorHistory\key($this->file), $before, $after);
		WeathermapEditorHistory\persist();
		self::assertSame(1, $this->history('history_status')['undo']);
		$result = $this->history('undo');
		self::assertSame(base64_decode($before['contents']), file_get_contents($this->file));
		self::assertNotSame($large, file_get_contents($this->file));
		self::assertSame(0, $result['undo']);
		self::assertSame(0, $result['redo']);
		self::assertLessThanOrEqual(8 * 1024 * 1024, filesize(WeathermapEditorHistory\storagePath()));
		self::assertSame($result, $this->history('history_status'));
	}

	public function testUnchangedDialogAfterUndoPreservesOriginalBytesAndBothStacks(): void {
		$original = file_get_contents($this->file);
		$this->move(60);
		$history = $this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
		$this->request('set_node_properties', ['node_name' => 'a', 'node_new_name' => 'a', 'node_label' => 'A',
			'node_infourl' => '', 'node_hover' => '', 'node_x' => 40, 'node_y' => 50, 'node_iconfilename' => '--NONE--']);
		setNodeProperties($this->file);
		self::assertSame(200, http_response_code());
		self::assertSame($original, file_get_contents($this->file));
		self::assertSame($history, $this->history('history_status'));
		$this->history('redo');
		self::assertSame(60, (int) $this->map()->nodes['a']->x);
	}

	public function testIntentionalRawFormattingEditsStillConsumeAStep(): void {
		$original = file_get_contents($this->file);
		$this->request('set_node_config', ['node_name' => 'a', 'item_configtext' => "NODE a\n # retain this raw comment\n LABEL A\n POSITION 40 50\n"]);
		setNodeConfig($this->file);
		self::assertStringContainsString('# retain this raw comment', file_get_contents($this->file));
		self::assertSame(1, $this->history('history_status')['undo']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
	}

	public function testQuotaMeasuresActualJsonBytesForSlashHeavyBase64Snapshots(): void {
		file_put_contents($this->file, file_get_contents($this->file) . str_repeat('#' . str_repeat("\xEF\xBF\xBF", 255) . "\n", 6800));
		$snapshot = WeathermapEditorHistory\snapshot($this->map(), $this->file);
		self::assertLessThan(8 * 1024 * 1024, strlen(serialize($snapshot)));
		self::assertGreaterThan(8 * 1024 * 1024, strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)));
		$this->move(60);
		self::assertSame(0, $this->history('history_status')['undo']);
		clearstatcache(true, WeathermapEditorHistory\storagePath());
		self::assertLessThanOrEqual(8 * 1024 * 1024, filesize(WeathermapEditorHistory\storagePath()));
	}

	public function testConfigurationRenderingFailureDoesNotWriteTheDestination(): void {
		$map = $this->map();
		$before = file_get_contents($this->file);
		stream_wrapper_unregister('php');
		try {
			self::assertFalse(@$map->ConfigText());
			self::assertFalse(@$map->WriteConfig($this->file));
		} finally { stream_wrapper_restore('php'); }
		self::assertSame($before, file_get_contents($this->file));
	}

	public function testUntouchedRawNodeAndLinkDialogsPreserveBothStacksAndExactRootBytes(): void {
		foreach (['node' => 'a', 'link' => 'a-b'] as $type => $name) {
			$original = file_get_contents($this->file);
			$this->move(60);
			$history = $this->history('undo');
			$this->request('fetch_config', ['item_type' => $type, 'item_name' => $name]);
			ob_start(); fetchConfig($this->file); $dialog = ob_get_clean();
			foreach (["\n", "\r\n", "\r"] as $newline) {
				$this->request('set_' . $type . '_config', [$type . '_name' => $name, 'item_configtext' => str_replace("\n", $newline, $dialog)]);
				$save = $type === 'node' ? 'setNodeConfig' : 'setLinkConfig';
				$save($this->file);
				self::assertSame(200, http_response_code());
				self::assertSame($original, file_get_contents($this->file));
				self::assertSame($history, $this->history('history_status'));
			}
			$this->history('redo');
			self::assertSame(60, (int) $this->map()->nodes['a']->x);
			$this->history('undo');
		}
	}

	public function testIntentionalRawWhitespaceEditsStillClearRedoAndCanBeUndone(): void {
		$this->move(60);
		$this->history('undo');
		$original = file_get_contents($this->file);
		$this->request('fetch_config', ['item_type' => 'link', 'item_name' => 'a-b']);
		ob_start(); fetchConfig($this->file); $dialog = ob_get_clean();
		$changed = str_replace('BANDWIDTH', ' BANDWIDTH', $dialog) . "\n";
		$this->request('set_link_config', ['link_name' => 'a-b', 'item_configtext' => $changed]);
		setLinkConfig($this->file);
		self::assertNotSame($original, file_get_contents($this->file));
		$status = $this->history('history_status');
		self::assertSame(1, $status['undo']);
		self::assertSame(0, $status['redo']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
	}

	private function saveColours(array $data): void {
		$this->request('set_map_colours', ['colours_json' => json_encode($data)]);
		ob_start(); wm_editor_colours($this->file, 'set_map_colours'); $response = json_decode(ob_get_clean(), true);
		self::assertSame(200, http_response_code());
		self::assertArrayNotHasKey('error', $response);
	}

	public function testUntouchedColoursAfterUndoKeepBytesAndRedoWithoutAnExplicitPulseHint(): void {
		require_once 'lib/editor.colours.php';
		file_put_contents($this->file, "SCALE DEFAULT 0 100 0 255 0 0\n" . file_get_contents($this->file));
		$original = file_get_contents($this->file);
		$this->move(60);
		$history = $this->history('undo');
		$data = wm_asset_colour_data($this->map());
		foreach ($data['rows'] as &$row) {
			$row['bottom'] = number_format($row['bottom'], 6, '.', '');
			$row['top'] = number_format($row['top'], 6, '.', '');
		}
		unset($row);
		$this->saveColours($data);
		self::assertSame($original, file_get_contents($this->file));
		self::assertSame($history, $this->history('history_status'));
		// Numeric-looking labels remain text and must not compare loosely.
		$data['rows'][0]['tag'] = '00';
		$this->saveColours($data);
		self::assertSame('00', wm_asset_colour_data($this->map())['rows'][0]['tag']);
		self::assertSame(0, $this->history('history_status')['redo']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
		$data = wm_asset_colour_data($this->map()); $data['pulse'] = false;
		$this->saveColours($data);
		self::assertFalse(wm_asset_colour_data($this->map())['pulse']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
	}

	public function testRedoCannotReintroduceADeletedImageUsedOnlyThroughAnExternalInclude(): void {
		$asset = 'images/objects/history-include-' . bin2hex(random_bytes(6)) . '.png';
		file_put_contents($asset, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
		$include = $this->directory . '/external.inc';
		file_put_contents($include, "NODE included\n LABEL Included\n ICON $asset\n POSITION 300 100\n");
		$original = file_get_contents($this->file);
		try {
			$this->request('set_map_properties');
			$map = $this->map();
			$map->has_includes = true;
			$map->include_directives[] = $include;
			self::assertTrue(wm_editor_write_config($map, $this->file));
			self::assertSame(200, http_response_code());
			self::assertArrayHasKey('included', $this->map()->nodes);
			self::assertStringNotContainsString($asset, file_get_contents($this->file));
			self::assertContains($asset, WeathermapEditorHistory\snapshot($this->map(), $this->file)['assets']);
			$this->history('undo');
			self::assertSame($original, file_get_contents($this->file));
			self::assertArrayNotHasKey('error', $this->history('redo'));
			self::assertArrayHasKey('included', $this->map()->nodes);
			$this->history('undo');
			unlink($asset);
			self::assertArrayHasKey('error', $this->history('redo'));
			self::assertSame(500, http_response_code());
			self::assertSame($original, file_get_contents($this->file));
			self::assertSame(1, $this->history('history_status')['redo']);
		} finally { if (is_file($asset)) unlink($asset); }
	}

	public function testOlderHistoryWithoutCompleteAssetMetadataIsDiscardedSafely(): void {
		$this->move(60);
		$original = file_get_contents($this->file);
		WeathermapEditorHistory\load();
		$key = WeathermapEditorHistory\key($this->file);
		unset($GLOBALS['weathermap_editor_history'][$key]['undo'][0]['snapshot']['assets']);
		WeathermapEditorHistory\persist();
		self::assertSame(0, $this->history('history_status')['undo']);
		self::assertSame($original, file_get_contents($this->file));
		$this->move(80);
		self::assertSame(1, $this->history('history_status')['undo']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
	}

	public function testUntouchedColoursWithIncludedNamedScalePreserveRedoAndLocalRowOrderMatters(): void {
		require_once 'lib/editor.colours.php';
		$include = $this->directory . '/shared-scale.conf';
		file_put_contents($include, "SCALE Shared 0 50 0 255 0 Low\nSCALE Shared 50 100 255 0 0 High\n");
		file_put_contents($this->file, "INCLUDE $include\n" . file_get_contents($this->file));
		$original = file_get_contents($this->file);
		$this->move(60); $history = $this->history('undo');
		$data = wm_asset_colour_data($this->map());
		$this->saveColours($data);
		self::assertSame($original, file_get_contents($this->file));
		self::assertSame($history, $this->history('history_status'));
		$indices = array_keys(array_filter($data['rows'], fn($row) => $row['scale'] === 'DEFAULT'));
		[$data['rows'][$indices[0]], $data['rows'][$indices[1]]] = [$data['rows'][$indices[1]], $data['rows'][$indices[0]]];
		$this->saveColours($data);
		self::assertNotSame($original, file_get_contents($this->file));
		self::assertSame(0, $this->history('history_status')['redo']);
		$this->history('undo');
		self::assertSame($original, file_get_contents($this->file));
	}

	public function testUndoRedoSynchronizeTheRegisteredTitleCacheOnlyWhenTitleChanges(): void {
		file_put_contents($this->file, "TITLE Original title\n" . file_get_contents($this->file));
		$this->request('set_map_properties', ['map_title' => 'Changed title', 'map_legend' => 'Traffic', 'map_stamp' => 'Created',
			'map_width' => 600, 'map_height' => 300, 'map_bgfile' => '--NONE--', 'map_linkdefaultwidth' => 7,
			'map_linkdefaultbwin' => '100M', 'map_linkdefaultbwout' => '100M']);
		setMapProperties($this->file);
		$GLOBALS['__test_db_calls'] = [];
		$this->history('history_status'); self::assertSame([], $GLOBALS['__test_db_calls']);
		$this->history('undo');
		self::assertSame('Original title', $this->map()->title);
		self::assertSame(['Original title', basename($this->file)], $GLOBALS['__test_db_calls'][0]['params']);
		self::assertStringContainsString('titlecache', $GLOBALS['__test_db_calls'][0]['sql']);
		$GLOBALS['__test_db_calls'] = [];
		$this->history('redo');
		self::assertSame('Changed title', $this->map()->title);
		self::assertSame(['Changed title', basename($this->file)], $GLOBALS['__test_db_calls'][0]['params']);
		$this->move(80); $GLOBALS['__test_db_calls'] = [];
		$this->history('undo');
		self::assertSame([], $GLOBALS['__test_db_calls'], 'position restores do not update the title cache');
		$this->request('redo', ['config_revision' => str_repeat('0', 64)]);
		ob_start(); WeathermapEditorHistory\handle($this->file, 'redo'); ob_end_clean();
		self::assertSame(409, http_response_code());
		self::assertSame([], $GLOBALS['__test_db_calls'], 'failed restores do not update the title cache');
	}

}
