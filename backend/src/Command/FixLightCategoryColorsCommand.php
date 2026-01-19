<?php

namespace App\Command;

use App\Category\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-light-category-colors',
    description: 'Vervangt te lichte categoriekleuren (wit/bijna-wit) door pastelkleuren',
)]
class FixLightCategoryColorsCommand extends Command
{
    private const PASTEL_COLORS = [
        '#FF6B6B', '#E07A5F', '#F87171', '#FB7185', '#F472B6', '#EC4899',
        '#FF8C42', '#FB923C', '#FDBA74', '#FBBF24', '#FCD34D', '#FACC15',
        '#4ADE80', '#34D399', '#10B981', '#22C55E', '#84CC16', '#A3E635',
        '#38BDF8', '#0EA5E9', '#3B82F6', '#60A5FA', '#22D3EE', '#06B6D4',
        '#A78BFA', '#8B5CF6', '#A855F7', '#C084FC', '#D946EF', '#E879F9',
        '#FDA4AF', '#BEF264', '#86EFAC', '#67E8F9', '#A5B4FC', '#C4B5FD',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Toon wat er zou veranderen zonder wijzigingen door te voeren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Fix Light Category Colors');

        if ($dryRun) {
            $io->note('Dry-run modus: er worden geen wijzigingen opgeslagen');
        }

        // Haal alle categorieën op
        $categories = $this->categoryRepository->findAll();
        $io->info(sprintf('Gevonden: %d categorieën', count($categories)));

        $updated = 0;
        $changes = [];

        foreach ($categories as $category) {
            $currentColor = $category->getColor();

            if ($currentColor === null || $this->isColorTooLight($currentColor)) {
                $newColor = $this->getRandomPastelColor();
                $changes[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'oldColor' => $currentColor ?? '(geen)',
                    'newColor' => $newColor,
                ];

                if (!$dryRun) {
                    $category->setColor($newColor);
                }
                $updated++;
            }
        }

        if (count($changes) > 0) {
            $io->section('Wijzigingen');
            $io->table(
                ['ID', 'Naam', 'Oude kleur', 'Nieuwe kleur'],
                array_map(fn($c) => [$c['id'], $c['name'], $c['oldColor'], $c['newColor']], $changes)
            );
        }

        if (!$dryRun && $updated > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('%d categorieën bijgewerkt met nieuwe kleuren', $updated));
        } elseif ($dryRun && $updated > 0) {
            $io->warning(sprintf('%d categorieën zouden worden bijgewerkt (dry-run)', $updated));
        } else {
            $io->success('Geen categorieën met te lichte kleuren gevonden');
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a color is too light (brightness > 230 on 0-255 scale)
     */
    private function isColorTooLight(string $hex): bool
    {
        $color = ltrim(strtolower($hex), '#');

        // Handle named colors
        if ($color === 'white' || $color === 'fff') {
            return true;
        }

        if (strlen($color) === 3) {
            // Expand shorthand (e.g., #fff -> #ffffff)
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        if (strlen($color) !== 6) {
            return false;
        }

        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        // Calculate perceived brightness (YIQ formula)
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $brightness > 230;
    }

    private function getRandomPastelColor(): string
    {
        return self::PASTEL_COLORS[array_rand(self::PASTEL_COLORS)];
    }
}
