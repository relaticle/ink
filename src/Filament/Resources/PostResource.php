<?php

declare(strict_types=1);

namespace Relaticle\Ink\Filament\Resources;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;
use RalphJSmit\Filament\SEO\SEO;
use Relaticle\Ink\Enums\PostStatus;
use Relaticle\Ink\Filament\Resources\PostResource\Pages\CreatePost;
use Relaticle\Ink\Filament\Resources\PostResource\Pages\EditPost;
use Relaticle\Ink\Filament\Resources\PostResource\Pages\ListPosts;
use Relaticle\Ink\Ink;
use Relaticle\Ink\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->maxLength(255)
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('locale', $get('locale') ?? Ink::defaultLocale()),
                                    ),

                                MarkdownEditor::make('content')
                                    ->required()
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'strike', 'link'],
                                        ['heading'],
                                        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                        ['table', 'attachFiles'],
                                        ['undo', 'redo'],
                                    ])
                                    ->columnSpanFull(),

                                Textarea::make('excerpt')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->afterHeader([
                                Action::make('view')
                                    ->icon('heroicon-o-eye')
                                    ->color('gray')
                                    ->url(fn (Post $record): string => $record->getUrl())
                                    ->openUrlInNewTab()
                                    ->visible(fn (?Post $record): bool => $record !== null),
                            ])
                            ->schema([
                                Toggle::make('status')
                                    ->label('Published')
                                    ->default(false)
                                    ->afterStateHydrated(fn ($component, $record) => $component->state($record?->status === PostStatus::Published))
                                    ->dehydrateStateUsing(fn (bool $state): string => $state ? PostStatus::Published->value : PostStatus::Draft->value),

                                DateTimePicker::make('published_at'),
                            ]),

                        Section::make('Language')
                            ->visible(fn (): bool => count(Ink::locales()) > 1)
                            ->schema([
                                Select::make('locale')
                                    ->options(fn (): array => collect(Ink::locales())->mapWithKeys(fn (string $locale): array => [$locale => strtoupper($locale)])->all())
                                    ->default(fn (): string => Ink::defaultLocale())
                                    ->required()
                                    ->live()
                                    ->rule(fn (Get $get, ?Post $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                        if ($record?->exists && empty($get('translation_of')) && $value !== $record->locale && $record->groupHasOtherIn((string) $value)) {
                                            $fail('Another post in this translation group already uses this language.');
                                        }
                                    }),

                                Select::make('translation_of')
                                    ->label('Translation of')
                                    ->searchable()
                                    ->dehydrated(false)
                                    ->getSearchResultsUsing(fn (string $search, Get $get, ?Post $record): array => Post::query()
                                        ->where('locale', '!=', $get('locale') ?? Ink::defaultLocale())
                                        ->whereLike('title', "%{$search}%", caseSensitive: false)
                                        ->when($record?->exists, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn (Post $post): array => [$post->getKey() => strtoupper($post->locale).': '.$post->title])
                                        ->all())
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => Post::find($value)?->title)
                                    ->rule(fn (Get $get, ?Post $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                        if (Post::find($value)?->hasVersionIn($get('locale') ?? Ink::defaultLocale(), $record)) {
                                            $fail('That post already has a version in this language.');
                                        }
                                    })
                                    ->helperText('Link this post to the post it translates.'),
                            ]),

                        Section::make('Details')
                            ->schema([
                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                    ]),

                                Select::make('tags')
                                    ->visible(fn () => (bool) config('ink.features.tags', false))
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                    ]),

                                Select::make('author_id')
                                    ->relationship('author', 'name')
                                    ->default(fn () => auth()->id())
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Section::make('Featured Image')
                            ->schema([
                                self::featuredImageField(),
                            ]),

                        Section::make('SEO')
                            ->schema([
                                SEO::make(['title', 'description']),
                            ])
                            ->collapsible()
                            ->persistCollapsed(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('locale')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->visible(fn (): bool => count(Ink::locales()) > 1)
                    ->sortable(),

                TextColumn::make('category.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Post $record): string => $record->displayStatus()['label'])
                    ->color(fn (Post $record): string => $record->displayStatus()['color']),

                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(fn (): array => collect(Ink::locales())->mapWithKeys(fn (string $locale): array => [$locale => strtoupper($locale)])->all())
                    ->visible(fn (): bool => count(Ink::locales()) > 1),
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Post $record): string => static::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Post $record): string => $record->getUrl())
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Post $post) => $post->forceFill([
                                'status' => PostStatus::Published,
                                'published_at' => $post->published_at ?? now(),
                            ])->save());
                            Notification::make()->success()->title('Posts published')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Post $post) => $post->forceFill([
                                'status' => PostStatus::Draft,
                                'published_at' => null,
                            ])->save());
                            Notification::make()->success()->title('Posts unpublished')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('schedule')
                        ->label('Schedule')
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            DateTimePicker::make('published_at')
                                ->label('Publish at')
                                ->required()
                                ->minDate(now()),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            $records->each(fn (Post $post) => $post->forceFill([
                                'status' => PostStatus::Published,
                                'published_at' => $data['published_at'],
                            ])->save());
                            Notification::make()->success()->title('Posts scheduled')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Returns the FileUpload component for the featured image. When the
     * media_library feature flag is on AND spatie/laravel-medialibrary's
     * Filament integration is installed, returns the SpatieMediaLibraryFileUpload
     * component instead. Falls back to a plain FileUpload otherwise so the
     * package never crashes on missing optional dependencies.
     */
    protected static function featuredImageField(): Field
    {
        $useMediaLibrary = (bool) config('ink.features.media_library', false)
            && class_exists(SpatieMediaLibraryFileUpload::class);

        if ($useMediaLibrary) {
            return SpatieMediaLibraryFileUpload::make('featured_image')
                ->collection('featured_image')
                ->image();
        }

        return FileUpload::make('featured_image')
            ->image()
            ->disk('public')
            ->directory('ink');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
