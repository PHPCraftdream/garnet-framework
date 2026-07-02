<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    class ImageCrop {
        public function __construct(
            public int $x,
            public int $y,
            public int $w,
            public int $h,
        ) {
        }

        public static function fromPost(array $crop): ImageCrop {
            return new ImageCrop(
                intval($crop['x'] ?? 0),
                intval($crop['y'] ?? 0),
                intval($crop['width'] ?? 900),
                intval($crop['height'] ?? 900),
            );
        }

        public function scale(float $scale): void {
            $this->x = intval(round($this->x * $scale));
            $this->y = intval(round($this->y * $scale));
            $this->w = intval(round($this->w * $scale));
            $this->h = intval(round($this->h * $scale));
        }

        public function json(): string {
            $result = json_encode([
                'x' => $this->x,
                'y' => $this->y,
                'w' => $this->w,
                'h' => $this->h,
            ]);

            return $result ?: '{}';
        }

        public function isEqual(ImageCrop $to): bool {
            return $this->x === $to->x &&
                $this->y === $to->y &&
                $this->w === $to->w &&
                $this->h === $to->h;
        }
    }
}
