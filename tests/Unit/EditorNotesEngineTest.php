<?php
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class EditorNotesEngineTest extends TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRealEngineRegression(): void {
        ob_start();
        try {
            require dirname(__DIR__) . '/Support/EditorNotesEngineRegression.php';
            $result = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertSame("PASS: note create, render, edit, styles, multiline, validation and delete\n", $result);
    }
}
