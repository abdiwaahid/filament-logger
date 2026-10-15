<?php

namespace Abdiwaahid\FilamentLogger\Resources;

use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ViewActivity;
use Abdiwaahid\FilamentLogger\Support\DateRange;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Activity as ActivityModel;

class ActivityResource extends Resource
{
    protected static ?string $pluginNavigationGroup = null;
    protected static ?string $label = 'Activity Log';
    protected static ?string $slug = 'activity-logs';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::ClipboardDocumentList;


    public static function getCluster(): ?string
    {
        return config('filament-logger.resources.cluster');
    }

    public static function setNavigationGroup(?string $group): void
    {
        static::$pluginNavigationGroup = $group;
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Group::make([
                    Section::make([
                        TextInput::make('causer_id')
                            ->afterStateHydrated(function ($component, ?Model $record) {
                                /** @phpstan-ignore-next-line */
                                return $component->state($record->causer?->name);
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.user')),

                        TextInput::make('subject_type')
                            ->afterStateHydrated(function ($component, ?Model $record, $state) {
                                /** @var Activity&ActivityModel $record */
                                return $state ? $component->state(Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.subject')),

                        Textarea::make('description')
                            ->label(__('filament-logger::filament-logger.resource.label.description'))
                            ->rows(2)
                            ->columnSpan('full'),
                    ])
                    ->columns(2),
                ])
                ->columnSpan(['sm' => 3]),

                Group::make([
                    Section::make([
                        TextEntry::make('log_name')
                            ->state(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->log_name ? ucwords($record->log_name) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.type')),

                        TextEntry::make('event')
                            ->state(function (?Model $record): string {
                                /** @phpstan-ignore-next-line */
                                return $record?->event ? ucwords($record?->event) : '-';
                            })
                            ->label(__('filament-logger::filament-logger.resource.label.event')),

                        TextEntry::make('created_at')
                            ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                            ->state(function (?Model $record): string {
                                /** @var Activity&ActivityModel $record */
                                return $record->created_at ? "{$record->created_at->format(config('filament-logger.datetime_format', 'd/m/Y H:i:s'))}" : '-';
                            }),
                    ])
                ]),
                Section::make()
                    ->columns()
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->properties?->count() > 0)
                    ->schema(function (?Model $record) {
                        /** @var Activity&ActivityModel $record */
                        $properties = $record->properties->except(['attributes', 'old']);

                        $schema = [];

                        if ($properties->count()) {
                            $schema[] = KeyValue::make('properties')
                                ->label(__('filament-logger::filament-logger.resource.label.properties'))
                                ->columnSpan('full');
                        }

                        if ($old = $record->properties->get('old')) {
                            $schema[] = KeyValue::make('old')
                                ->afterStateHydrated(fn (KeyValue $component) => $component->state($old))
                                ->label(__('filament-logger::filament-logger.resource.label.old'));
                        }

                        if ($attributes = $record->properties->get('attributes')) {
                            $schema[] = KeyValue::make('attributes')
                                ->afterStateHydrated(fn (KeyValue $component) => $component->state($attributes))
                                ->label(__('filament-logger::filament-logger.resource.label.new'));
                        }

                        return $schema;
                    }),
            ])
            ->columns(['sm' => 4, 'lg' => null]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->badge()
                    ->colors(static::getLogNameColors())
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->formatStateUsing(fn ($state) => ucwords($state))
                    ->sortable(),

            TextColumn::make('event')
                ->label(__('filament-logger::filament-logger.resource.label.event'))
                    ->sortable(),

                TextColumn::make('description')
            ->label(__('filament-logger::filament-logger.resource.label.description'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->wrap(),

                TextColumn::make('subject_type')
                    ->label(__('filament-logger::filament-logger.resource.label.subject'))
                    ->formatStateUsing(function ($state, Model $record) {
                        /** @var Activity&ActivityModel $record */
                        if (!$state) {
                            return '-';
                        }
                        return Str::of($state)->afterLast('\\')->headline().' # '.$record->subject_id;
                    }),

                TextColumn::make('causer.name')
                    ->label(__('filament-logger::filament-logger.resource.label.user')),

                TextColumn::make('created_at')
                    ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                    ->dateTime(config('filament-logger.datetime_format', 'd/m/Y H:i:s'), config('app.timezone'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('filament-logger::filament-logger.resource.label.type'))
                    ->multiple()
                    ->options(fn (): array => static::getLogNameList()),

                SelectFilter::make('event')
                    ->label(__('filament-logger::filament-logger.resource.label.event'))
                    ->multiple()
                    ->options(fn (): array => static::getEventList()),

                SelectFilter::make('subject_type')
                    ->label(__('filament-logger::filament-logger.resource.label.subject_type'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => static::getSubjectTypeList()),

                Filter::make('causer')
                    ->label(__('filament-logger::filament-logger.resource.label.user'))
                    ->schema([
                        Select::make('causer_id')
                            ->label(__('filament-logger::filament-logger.resource.label.user'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchCausers($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::getCauserLabel($value)),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $causerId = $data['causer_id'] ?? null;
                        $causerModel = static::getCauserModel();

                        if (blank($causerId) || ! $causerModel) {
                            return $query;
                        }

                        return $query
                            ->where('causer_type', (new $causerModel)->getMorphClass())
                            ->where('causer_id', $causerId);
                    })
                    ->indicateUsing(function (array $data): array {
                        $causerId = $data['causer_id'] ?? null;

                        if (blank($causerId)) {
                            return [];
                        }

                        return [
                            __('filament-logger::filament-logger.filter.indicator.causer', [
                                'name' => static::getCauserLabel($causerId) ?? $causerId,
                            ]),
                        ];
                    }),

                Filter::make('created_at')
                    ->label(__('filament-logger::filament-logger.resource.label.logged_at'))
                    ->columns(2)
                    ->schema([
                        Select::make('preset')
                            ->label(__('filament-logger::filament-logger.filter.period'))
                            ->options(fn (): array => static::getDatePresetOptions())
                            ->columnSpanFull(),

                        DatePicker::make('from')
                            ->label(__('filament-logger::filament-logger.filter.from'))
                            ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),

                        DatePicker::make('until')
                            ->label(__('filament-logger::filament-logger.filter.until'))
                            ->displayFormat(config('filament-logger.date_format', 'd/m/Y')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => DateRange::apply($query, $data))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $dateFormat = config('filament-logger.date_format', 'd/m/Y');

                        if ($preset = $data['preset'] ?? null) {
                            $indicators[] = __('filament-logger::filament-logger.filter.indicator.preset', [
                                'period' => static::getDatePresetOptions()[$preset] ?? $preset,
                            ]);
                        }

                        if ($from = $data['from'] ?? null) {
                            $indicators[] = __('filament-logger::filament-logger.filter.indicator.from', [
                                'date' => Carbon::parse($from)->format($dateFormat),
                            ]);
                        }

                        if ($until = $data['until'] ?? null) {
                            $indicators[] = __('filament-logger::filament-logger.filter.indicator.until', [
                                'date' => Carbon::parse($until)->format($dateFormat),
                            ]);
                        }

                        return $indicators;
                    }),

                 Filter::make('properties->old')
					->indicateUsing(function (array $data): ?string {
						$old = data_get($data, 'old');
						if (blank($old)) {
							return null;
						}

						return __('filament-logger::filament-logger.resource.label.old_attributes') . $old;
					})
					->schema([
						TextInput::make('old')
							->label(__('filament-logger::filament-logger.resource.label.old'))
							->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
					])
					->query(function (Builder $query, array $data): Builder {
						$old = data_get($data, 'old');
						if (blank($old)) {
							return $query;
						}

						return $query->where('properties->old', 'like', "%{$old}%");
					}),

				Filter::make('properties->attributes')
					->indicateUsing(function (array $data): ?string {
						$new = data_get($data, 'new');
						if (blank($new)) {
							return null;
						}

						return __('filament-logger::filament-logger.resource.label.new_attributes') . $new;
					})
					->schema([
						TextInput::make('new')
							->label(__('filament-logger::filament-logger.resource.label.new'))
							->hint(__('filament-logger::filament-logger.resource.label.properties_hint')),
					])
					->query(function (Builder $query, array $data): Builder {
						$new = data_get($data, 'new');
						if (blank($new)) {
							return $query;
						}

						return $query->where('properties->attributes', 'like', "%{$new}%");
					}),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }

    public static function getModel(): string
    {
        return ActivitylogServiceProvider::determineActivityModel();
    }

    /**
     * Subject types offered by the "Subject type" filter: the models behind Filament resources,
     * the models registered in the config, and any subject type already present in the table.
     *
     * @return array<string, string>
     */
    protected static function getSubjectTypeList(): array
    {
        $subjects = [];

        if (config('filament-logger.resources.enabled', true)) {
            $exceptResources = [...config('filament-logger.resources.exclude', []), config('filament-logger.activity_resource')];
            $removedExcludedResources = collect(Filament::getResources())->filter(function ($resource) use ($exceptResources) {
                return ! in_array($resource, $exceptResources);
            });
            foreach ($removedExcludedResources as $resource) {
                $model = $resource::getModel();
                $subjects[$model] = (string) Str::of(class_basename($model))->headline();
            }
        }

        if (config('filament-logger.models.enabled', true)) {
            foreach (config('filament-logger.models.register', []) as $model) {
                $subjects[$model] = (string) Str::of(class_basename($model))->headline();
            }
        }

        foreach (static::getDistinctValues('subject_type') as $subjectType) {
            $subjects[$subjectType] ??= (string) Str::of(class_basename($subjectType))->headline();
        }

        return $subjects;
    }

    /**
     * Log names enabled in the config (including custom ones), without touching the database.
     *
     * @return array<int, string>
     */
    public static function getConfiguredLogNames(): array
    {
        $names = [];

        foreach (['resources', 'models', 'access', 'notifications'] as $group) {
            if (config("filament-logger.{$group}.enabled")) {
                $names[] = config("filament-logger.{$group}.log_name");
            }
        }

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            $names[] = $custom['log_name'] ?? null;
        }

        return array_values(array_unique(array_filter($names, 'filled')));
    }

    /**
     * Log names offered by the "Type" filter: the configured ones first,
     * then any other log name already present in the table.
     *
     * @return array<string, string>
     */
    protected static function getLogNameList(): array
    {
        return array_replace(
            static::toOptions(static::getConfiguredLogNames()),
            static::toOptions(static::getDistinctValues('log_name')),
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function getEventList(): array
    {
        return static::toOptions(static::getDistinctValues('event'));
    }

    /**
     * @return array<string, string>
     */
    protected static function getDatePresetOptions(): array
    {
        $options = [];

        foreach (DateRange::PRESETS as $preset) {
            $options[$preset] = __("filament-logger::filament-logger.filter.preset.{$preset}");
        }

        return $options;
    }

    protected static function getCauserModel(): ?string
    {
        $model = config('filament-logger.filters.causer_model') ?: config('auth.providers.users.model');

        return is_string($model) && class_exists($model) ? $model : null;
    }

    /**
     * @return array<int|string, string>
     */
    protected static function searchCausers(string $search): array
    {
        $model = static::getCauserModel();

        if (! $model) {
            return [];
        }

        $column = config('filament-logger.filters.causer_search_column', 'name');

        return $model::query()
            ->where($column, 'like', "%{$search}%")
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $user): array => [$user->getKey() => static::getUserLabel($user)])
            ->all();
    }

    protected static function getCauserLabel(mixed $value): ?string
    {
        $model = static::getCauserModel();

        if (blank($value) || ! $model) {
            return null;
        }

        $user = $model::query()->find($value);

        return $user ? static::getUserLabel($user) : null;
    }

    protected static function getUserLabel(Model $user): string
    {
        if ($user instanceof Authenticatable) {
            return Filament::getUserName($user);
        }

        return (string) $user->getAttribute(config('filament-logger.filters.causer_search_column', 'name'));
    }

    /**
     * Distinct non-null values of a column of the activity log, scoped like the resource itself
     * (so tenant scoping applies) and cached briefly to keep the filter dropdowns cheap.
     *
     * @return array<int, string>
     */
    protected static function getDistinctValues(string $column): array
    {
        return static::cachedOptions($column, fn (): array => static::getEloquentQuery()
            ->whereNotNull($column)
            ->distinct()
            ->reorder($column)
            ->pluck($column)
            ->map(fn ($value): string => (string) $value)
            ->all());
    }

    protected static function cachedOptions(string $key, Closure $callback): array
    {
        $ttl = (int) config('filament-logger.filters.options_cache_ttl', 60);

        if ($ttl <= 0) {
            return $callback();
        }

        $tenant = Filament::getTenant()?->getKey() ?? 'global';

        return Cache::remember("filament-logger:options:{$key}:{$tenant}", $ttl, $callback);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    protected static function toOptions(array $values): array
    {
        return $values ? array_combine($values, $values) : [];
    }

    protected static function getLogNameColors(): array
    {
        $colors = [];

        foreach (config('filament-logger.custom') ?? [] as $custom) {
            if (filled($custom['color'] ?? null)) {
                $colors[$custom['color']][] = $custom['log_name'];
            }
        }

        foreach (['resources', 'models', 'access', 'notifications'] as $group) {
            $color = config("filament-logger.{$group}.color");
            $logName = config("filament-logger.{$group}.log_name");

            if (config("filament-logger.{$group}.enabled") && filled($color) && filled($logName)) {
                $colors[$color][] = $logName;
            }
        }

        return array_map(fn (array $logNames): array => array_values(array_unique($logNames)), $colors);
    }

    public static function getLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.log');
    }

    public static function getPluralLabel(): string
    {
        return __('filament-logger::filament-logger.resource.label.logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$pluginNavigationGroup ?? __('filament-logger::filament-logger.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-logger::filament-logger.nav.log.label');
    }

    public static function getNavigationIcon(): string
    {
        return __('filament-logger::filament-logger.nav.log.icon');
    }

	public static function isScopedToTenant(): bool
    {
		return config('filament-logger.scoped_to_tenant', true);
    }

	public static function getNavigationSort(): ?int
	{
		return config('filament-logger.navigation_sort', null);
	}

	
}
