<?php

namespace App\Services\Kolesa;

use App\Exceptions\Kolesa\KolesaApiException;
use App\Exceptions\Kolesa\KolesaAuthExpiredException;
use App\Exceptions\Kolesa\KolesaNetworkException;
use App\Models\Advert;
use App\Models\KolesaSyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class KolesaSyncService
{
    private bool $hasRequested = false;

    public function __construct(private readonly KolesaClient $client)
    {
    }

    public function run(KolesaSyncRun $run): void
    {
        $this->hasRequested = false;

        $syncStartedAt = Carbon::now();

        $run->update([
            'status' => KolesaSyncRun::STATUS_RUNNING,
            'started_at' => $syncStartedAt,
        ]);

        try {
            $this->processSection(
                $run,
                KolesaSyncRun::SECTION_LIVE,
                Advert::STATUS_LIVE,
                $syncStartedAt,
                fn (int $offset) => $this->client->listLive($offset),
            );

            $this->processSection(
                $run,
                KolesaSyncRun::SECTION_ARCHIVE,
                Advert::STATUS_ARCHIVE,
                $syncStartedAt,
                fn (int $offset) => $this->client->listArchive($offset),
            );

            $run->update(['current_section' => KolesaSyncRun::SECTION_FINALIZE]);

            $removed = Advert::query()
                ->where('last_seen_at', '<', $syncStartedAt)
                ->whereIn('status', [Advert::STATUS_LIVE, Advert::STATUS_ARCHIVE])
                ->update([
                    'status' => Advert::STATUS_DELETE,
                    'removed_at' => Carbon::now(),
                    'last_seen_at' => Carbon::now(),
                ]);

            $this->pruneCarts();

            $run->update([
                'status' => KolesaSyncRun::STATUS_COMPLETED,
                'finished_at' => Carbon::now(),
                'current_section' => null,
                'marked_removed_count' => $removed,
            ]);
        } catch (KolesaAuthExpiredException $e) {
            $this->fail($run, KolesaSyncRun::ERR_AUTH_EXPIRED, $e->getMessage());
        } catch (KolesaNetworkException $e) {
            $this->fail($run, KolesaSyncRun::ERR_NETWORK, $e->getMessage());
        } catch (KolesaApiException $e) {
            $this->fail($run, KolesaSyncRun::ERR_API, $e->getMessage());
        } catch (Throwable $e) {
            $this->fail($run, KolesaSyncRun::ERR_UNKNOWN, $e->getMessage());

            throw $e;
        }
    }

    private function processSection(
        KolesaSyncRun $run,
        string $section,
        string $advertStatus,
        Carbon $seenAt,
        \Closure $fetcher,
    ): void {
        $run->update(['current_section' => $section]);

        $offset = 0;
        $pageSize = (int) config('kolesa.page_size');
        $totalColumn = $section.'_total';
        $processedColumn = $section.'_processed';
        $offsetColumn = $section.'_offset';
        $totalSet = false;

        while (true) {
            $this->throttleBeforeRequest();

            $data = $fetcher($offset);

            if (! $totalSet) {
                $total = $this->extractTotal($data);

                if ($total !== null) {
                    $run->update([$totalColumn => $total]);
                }

                $totalSet = true;
            }

            $items = $this->extractItems($data);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $id = $this->extractAdvertId($item);

                if ($id === null) {
                    continue;
                }

                Advert::query()->updateOrCreate(
                    ['kolesa_id' => $id],
                    $this->mapItemToAdvertAttributes($item, $advertStatus, $seenAt),
                );
            }

            $count = count($items);
            $offset += $pageSize;

            $run->increment($processedColumn, $count);
            $run->update([$offsetColumn => $offset]);

            if ($count < $pageSize) {
                break;
            }
        }
    }

    private function throttleBeforeRequest(): void
    {
        if ($this->hasRequested) {
            $sleep = (int) config('kolesa.sleep_seconds');

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $this->hasRequested = true;
    }

    public function extractItems(array $payload): array
    {
        foreach (['items', 'data', 'adverts', 'list'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        if (isset($payload['data']['items']) && is_array($payload['data']['items'])) {
            return array_values($payload['data']['items']);
        }

        return [];
    }

    public function extractTotal(array $payload): ?int
    {
        foreach ([['total'], ['pagination', 'total'], ['meta', 'total'], ['data', 'total']] as $path) {
            $value = $payload;

            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;

                    break;
                }

                $value = $value[$segment];
            }

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return (int) $value;
            }
        }

        return null;
    }

    public function mapItemToAdvertAttributes(array $item, string $status, Carbon $seenAt): array
    {
        $title = data_get($item, 'data.title')
            ?? data_get($item, 'extra_data.title.at_page')
            ?? data_get($item, 'extra_data.title.in_list')
            ?? data_get($item, 'data.spare.name')
            ?? data_get($item, 'title')
            ?? data_get($item, 'name');

        $price = data_get($item, 'data.price')
            ?? data_get($item, 'data.price-user')
            ?? data_get($item, 'price');

        $publishedAt = data_get($item, 'service_data.live_at')
            ?? data_get($item, 'service_data.published_at')
            ?? data_get($item, 'data.add_date')
            ?? data_get($item, 'data.list_date')
            ?? data_get($item, 'created_at');

        $intStat = fn (string $path): int => (int) (is_numeric(data_get($item, $path))
            ? data_get($item, $path)
            : 0);

        $badges = data_get($item, 'system_data.badges', []);
        $activeServices = is_array($badges)
            ? array_values(array_filter($badges, 'is_string'))
            : [];

        $showTill = data_get($item, 'system_data.show_till');

        return [
            'title' => $title !== null ? (string) $title : null,
            'price' => is_numeric($price) ? (int) $price : null,
            'status' => $status,
            'photo_url' => $this->extractPhotoUrl($item),
            'views_total' => $intStat('statistics.nb_views.total'),
            'views_week' => $intStat('statistics.nb_views.last_week'),
            'phone_views_total' => $intStat('statistics.nb_phone_views.total'),
            'phone_views_week' => $intStat('statistics.nb_phone_views.last_week'),
            'favorites_total' => $intStat('statistics.added_to_favorite.total'),
            'favorites_week' => $intStat('statistics.added_to_favorite.last_week'),
            'active_services' => $activeServices,
            'show_till' => $showTill ? $this->parseDate($showTill) : null,
            'published_at' => $publishedAt ? $this->parseDate($publishedAt) : null,
            'last_seen_at' => $seenAt,
            'removed_at' => null,
            'raw' => $item,
        ];
    }

    private function extractPhotoUrl(array $item): ?string
    {
        $files = $item['Files'] ?? $item['files'] ?? null;

        if (is_array($files) && $files !== []) {
            $photoIds = (array) data_get($item, 'service_data.photo_ids', []);
            $firstFile = null;

            foreach ($photoIds as $pid) {
                $key = (string) $pid;
                if (isset($files[$key])) {
                    $firstFile = $files[$key];
                    break;
                }
            }

            if ($firstFile === null) {
                $firstFile = reset($files) ?: null;
            }

            if (is_array($firstFile)) {
                foreach ((array) ($firstFile['thumbnails'] ?? []) as $thumb) {
                    if (($thumb['width'] ?? null) === 400) {
                        return $thumb['path'] ?? null;
                    }
                }

                return data_get($firstFile, 'thumbnails.0.path')
                    ?? data_get($firstFile, 'path');
            }
        }

        $direct = data_get($item, 'photos.0.url')
            ?? data_get($item, 'photo')
            ?? data_get($item, 'image')
            ?? data_get($item, 'preview_photo');

        return is_string($direct) ? $direct : null;
    }

    private function extractAdvertId(array $item): ?int
    {
        $id = data_get($item, 'id') ?? data_get($item, 'advert_id');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            return (int) $id;
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Drop cart_items whose advert no longer belongs to the cart's storage
     * (e.g. active ad moved to archive on kolesa.kz, or got deleted).
     */
    private function pruneCarts(): void
    {
        $invalidIds = DB::table('cart_items')
            ->join('adverts', 'adverts.id', '=', 'cart_items.advert_id')
            ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->whereColumn('adverts.status', '!=', 'carts.storage_id')
            ->pluck('cart_items.id');

        if ($invalidIds->isEmpty()) {
            return;
        }

        $deleted = (int) DB::table('cart_items')
            ->whereIn('id', $invalidIds)
            ->delete();

        if ($deleted > 0) {
            Log::info('Pruned cart items after sync', ['count' => $deleted]);
        }
    }

    private function fail(KolesaSyncRun $run, string $errorType, string $message): void
    {
        $run->update([
            'status' => KolesaSyncRun::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'current_section' => null,
            'error_type' => $errorType,
            'error_message' => $message,
        ]);
    }
}
