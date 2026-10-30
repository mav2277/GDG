<?php

declare(strict_types=1);

final class NameTagLayoutController
{
    private const FONT_FAMILIES = [
        'Georgia, serif', "'Times New Roman', serif", 'Cambria, Georgia, serif',
        'Garamond, Georgia, serif', "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        "'Playfair Display', Georgia, serif", 'Arial, Helvetica, sans-serif',
        'Calibri, Arial, sans-serif', "'Segoe UI', Arial, sans-serif", 'Tahoma, sans-serif',
        'Verdana, sans-serif', "'Trebuchet MS', sans-serif", "'Century Gothic', Arial, sans-serif",
        "'Courier New', monospace", "Consolas, 'Courier New', monospace",
    ];

    public function handle(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        require_once dirname(__DIR__) . '/Models/NameTagLayout.php';
        $model = new NameTagLayout(database_connection());

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $eventId = filter_var($_GET['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($eventId === false) $this->respond(422, ['success' => false, 'message' => 'Choose a valid event.']);
            $exists = database_connection()->prepare('SELECT id FROM events WHERE id = :id');
            $exists->execute(['id' => $eventId]);
            if ($exists->fetchColumn() === false) $this->respond(404, ['success' => false, 'message' => 'That event could not be found.']);
            try {
                $layout = $model->forEvent((int) $eventId);
                if ($layout === null) $this->respond(200, ['success' => true, 'layout' => null]);
                $widthMm = (float) $layout['width_mm'];
                $heightMm = (float) $layout['height_mm'];
                $this->respond(200, ['success' => true, 'layout' => [
                    'artwork' => $model->artworkDataUrl((string) $layout['template_path']),
                    'originalName' => (string) $layout['original_file_name'],
                    'widthMm' => $widthMm,
                    'heightMm' => $heightMm,
                    'x' => (float) $layout['name_area_x_mm'] / $widthMm * 100,
                    'y' => (float) $layout['name_area_y_mm'] / $heightMm * 100,
                    'areaWidth' => (float) $layout['name_area_width_mm'] / $widthMm * 100,
                    'areaHeight' => (float) $layout['name_area_height_mm'] / $heightMm * 100,
                    'fontSize' => (float) $layout['font_size_pt'],
                    'fontFamily' => (string) $layout['font_family'],
                    'alignment' => (string) $layout['text_alignment'],
                    'textColor' => (string) $layout['text_color_hex'],
                ]]);
            } catch (Throwable $error) {
                error_log('Name-tag layout could not be loaded: ' . $error->getMessage());
                $this->respond(503, ['success' => false, 'message' => 'The saved name-tag layout could not be loaded.']);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: GET, POST');
            $this->respond(405, ['success' => false, 'message' => 'This action is not allowed.']);
        }

        if (!csrf_is_valid($_POST['csrfToken'] ?? null)) {
            $this->respond(400, ['success' => false, 'message' => 'Your session token expired. Refresh the page and try again.']);
        }
        $eventId = filter_var($_POST['eventId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $width = $this->positiveNumber($_POST['widthMm'] ?? null);
        $height = $this->positiveNumber($_POST['heightMm'] ?? null);
        $fontSize = $this->positiveNumber($_POST['fontSize'] ?? null);
        $x = $this->number($_POST['x'] ?? null);
        $y = $this->number($_POST['y'] ?? null);
        $areaWidth = $this->number($_POST['areaWidth'] ?? null);
        $areaHeight = $this->number($_POST['areaHeight'] ?? null);
        $fontFamily = $_POST['fontFamily'] ?? null;
        $alignment = $_POST['alignment'] ?? null;
        $textColor = $_POST['textColor'] ?? null;

        $bounds = [$x, $y, $areaWidth, $areaHeight];
        $bounds = array_map(static fn ($value): ?float => $value === null ? null : round($value, 2), $bounds);
        [$x, $y, $areaWidth, $areaHeight] = $bounds;
        if ($x !== null && $areaWidth !== null && $x + $areaWidth > 100) $areaWidth = round(100 - $x, 2);
        if ($y !== null && $areaHeight !== null && $y + $areaHeight > 100) $areaHeight = round(100 - $y, 2);
        if ($eventId === false || $width === null || $height === null || $fontSize === null
            || max($width, $height) > 3000 || $fontSize > 999.99
            || $x === null || $y === null || $areaWidth === null || $areaHeight === null
            || $x < 0 || $y < 0 || $areaWidth < 12 || $areaHeight < 8
            || $x + $areaWidth > 100 || $y + $areaHeight > 100
            || !is_string($fontFamily) || !in_array($fontFamily, self::FONT_FAMILIES, true)
            || !is_string($alignment) || !in_array($alignment, ['left', 'center', 'right'], true)
            || !is_string($textColor) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $textColor)) {
            $this->respond(422, ['success' => false, 'message' => 'Check the dimensions, name area, font, alignment, and #RRGGBB text colour.']);
        }

        $upload = null;
        if (isset($_FILES['artwork']) && $_FILES['artwork']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['artwork'];
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                $this->respond(422, ['success' => false, 'message' => 'The artwork upload did not complete. Choose the image again.']);
            }
            if ((int) $file['size'] < 1 || (int) $file['size'] > 10 * 1024 * 1024) {
                $this->respond(422, ['success' => false, 'message' => 'Name-tag artwork must be 10 MB or smaller.']);
            }
            $upload = [
                'tmpName' => $file['tmp_name'],
                'originalName' => mb_substr(basename((string) $file['name']), 0, 255),
            ];
        }

        try {
            $user = authenticated_user();
            $model->save((int) $eventId, (int) ($user['id'] ?? 0), [
                'widthMm' => $width, 'heightMm' => $height, 'fontSize' => $fontSize,
                'x' => $x, 'y' => $y, 'areaWidth' => $areaWidth, 'areaHeight' => $areaHeight,
                'fontFamily' => $fontFamily, 'alignment' => $alignment, 'textColor' => $textColor,
            ], $upload);
            $this->respond(200, ['success' => true, 'message' => 'Name-tag layout saved for this event.']);
        } catch (InvalidArgumentException $error) {
            $this->respond(422, ['success' => false, 'message' => $error->getMessage()]);
        } catch (OutOfBoundsException $error) {
            $this->respond(404, ['success' => false, 'message' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('Name-tag layout could not be saved: ' . $error->getMessage());
            $this->respond(503, ['success' => false, 'message' => 'The name-tag layout could not be saved. Refresh and try again.']);
        }
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_scalar($value) && is_numeric($value) && (float) $value > 0 && is_finite((float) $value)
            ? (float) $value : null;
    }

    private function number(mixed $value): ?float
    {
        return is_scalar($value) && is_numeric($value) && is_finite((float) $value)
            ? (float) $value : null;
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
