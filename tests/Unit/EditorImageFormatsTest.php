<?php
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class EditorImageFormatsTest extends TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRealEngineRegression(): void {
        ob_start();
        try {
            require dirname(__DIR__) . '/Support/EditorImageFormatsRegression.php';
            $result = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertSame("Editor image formats regression PASS\n", $result);
    }
}
