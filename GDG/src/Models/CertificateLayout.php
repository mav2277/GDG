<?php

declare(strict_types=1);

final class CertificateLayout
{
    public function __construct(private PDO $database)
    {
    }

    public function forEvent(int $eventId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, template_path, original_file_name, width_mm, height_mm,
                    name_area_x_mm, name_area_y_mm, name_area_width_mm, name_area_height_mm,
                    font_size_pt, font_family, text_alignment, text_color_hex
             FROM event_print_templates
             WHERE event_id = :event_id AND print_type = \'certificate\' AND is_active = 1
             ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['event_id' => $eventId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function save(int $eventId, int $userId, array $values, ?array $upload): array
    {
        $event = $this->database->prepare('SELECT id FROM events WHERE id = :id');
        $event->execute(['id' => $eventId]);
        if ($event->fetchColumn() === false) {
            throw new OutOfBoundsException('That event could not be found.');
        }

        $existing = $this->forEvent($eventId);
        if ($upload === null && $existing === null) {
            throw new InvalidArgumentException('Choose a certificate artwork image before saving this layout.');
        }

        $newRelativePath = null;
        if ($upload !== null) {
            $newRelativePath = $this->storeArtwork($upload);
            $relativePath = $newRelativePath;
            $originalName = $upload['originalName'];
        } else {
            $relativePath = (string) $existing['template_path'];
            $originalName = (string) $existing['original_file_name'];
            $this->resolveArtworkPath($relativePath);
        }

        try {
            $this->database->beginTransaction();
            $lockEvent = $this->database->prepare('SELECT id FROM events WHERE id = :event_id FOR UPDATE');
            $lockEvent->execute(['event_id' => $eventId]);
            if ($lockEvent->fetchColumn() === false) {
                throw new OutOfBoundsException('That event could not be found.');
            }
            $deactivate = $this->database->prepare(
                'UPDATE event_print_templates SET is_active = 0
                 WHERE event_id = :event_id AND print_type = \'certificate\' AND is_active = 1'
            );
            $deactivate->execute(['event_id' => $eventId]);

            $insert = $this->database->prepare(
                'INSERT INTO event_print_templates (
                    event_id, print_type, template_path, original_file_name, width_mm, height_mm,
                    name_area_x_mm, name_area_y_mm, name_area_width_mm, name_area_height_mm,
                    font_size_pt, font_family, text_alignment, text_color_hex,
                    is_active, uploaded_by_user_id
                 ) VALUES (
                    :event_id, \'certificate\', :template_path, :original_file_name, :width_mm, :height_mm,
                    :area_x_mm, :area_y_mm, :area_width_mm, :area_height_mm,
                    :font_size, :font_family, :alignment, :text_color, 1, :user_id
                 )'
            );
            $widthMm = round($values['widthInches'] * 25.4, 2);
            $heightMm = round($values['heightInches'] * 25.4, 2);
            $areaXmm = round($widthMm * $values['x'] / 100, 2);
            $areaYmm = round($heightMm * $values['y'] / 100, 2);
            $areaWidthMm = min(round($widthMm * $values['areaWidth'] / 100, 2), round($widthMm - $areaXmm, 2));
            $areaHeightMm = min(round($heightMm * $values['areaHeight'] / 100, 2), round($heightMm - $areaYmm, 2));
            $insert->execute([
                'event_id' => $eventId,
                'template_path' => $relativePath,
                'original_file_name' => $originalName,
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'area_x_mm' => $areaXmm,
                'area_y_mm' => $areaYmm,
                'area_width_mm' => $areaWidthMm,
                'area_height_mm' => $areaHeightMm,
                'font_size' => $values['fontSize'],
                'font_family' => $values['fontFamily'],
                'alignment' => $values['alignment'],
                'text_color' => $values['textColor'],
                'user_id' => $userId,
            ]);
            $templateId = (int) $this->database->lastInsertId();
            $this->database->commit();
            return ['id' => $templateId, 'templatePath' => $relativePath, 'originalName' => $originalName];
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            if ($newRelativePath !== null) {
                $this->deleteArtwork($newRelativePath);
            }
            throw $error;
        }
    }

    public function artworkDataUrl(string $relativePath): string
    {
        $path = $this->resolveArtworkPath($relativePath);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('The saved certificate artwork is unavailable. Upload it again.');
        }
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function storeArtwork(array $upload): string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmpName']);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Use a PNG, JPG, or WebP image for certificate artwork.');
        }
        $image = @getimagesize($upload['tmpName']);
        if ($image === false || $image[0] > 12000 || $image[1] > 12000 || $image[0] * $image[1] > 40000000) {
            throw new InvalidArgumentException('The certificate image dimensions are invalid or too large.');
        }

        $directory = dirname(__DIR__, 2) . '/storage/uploads/certificate-templates';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Certificate artwork storage is unavailable.');
        }
        $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($upload['tmpName'], $directory . '/' . $filename)) {
            throw new RuntimeException('The certificate artwork could not be stored. Try again.');
        }
        return 'certificate-templates/' . $filename;
    }

    private function resolveArtworkPath(string $relativePath): string
    {
        if (!preg_match('/^certificate-templates\/[a-f0-9]{40}\.(?:png|jpg|webp)$/', $relativePath)) {
            throw new RuntimeException('The saved certificate artwork is unavailable. Upload it again.');
        }
        $root = realpath(dirname(__DIR__, 2) . '/storage/uploads/certificate-templates');
        $path = realpath(dirname(__DIR__, 2) . '/storage/uploads/' . $relativePath);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new RuntimeException('The saved certificate artwork is unavailable. Upload it again.');
        }
        return $path;
    }

    private function deleteArtwork(string $relativePath): void
    {
        try {
            $path = $this->resolveArtworkPath($relativePath);
            if (is_file($path)) unlink($path);
        } catch (Throwable) {
            // Leave any path that cannot be positively verified untouched.
        }
    }
}
