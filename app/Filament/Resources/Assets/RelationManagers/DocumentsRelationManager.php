<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use App\Filament\Resources\AssetDocuments\Pages\CreateAssetDocument;
use App\Models\AssetDocument;
use App\Services\DocumentService;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Panel "Dokumen" di kartu aset: nol, satu, atau banyak dokumen digital per aset. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->hasPermission('document.view');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->documents()->whereIn('asset_documents.status', AssetDocument::LIVE)->count();

        return $n ? (string) $n : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $can = fn () => (bool) auth()->user()?->hasPermission('document.manage');
        $asset = $this->getOwnerRecord();
        $missing = $this->missingText();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->visibleTo(auth()->user())->with('type')->withCount('files'))
            ->recordTitle(fn (AssetDocument $record) => $record->label)
            ->description($missing)
            ->columns([
                TextColumn::make('register_no')->label('No. register')->fontFamily('mono'),
                TextColumn::make('title')->label('Dokumen')->wrap()->description(fn ($record) => $record->type?->code.($record->document_number ? ' · No. '.$record->document_number : '')),
                TextColumn::make('valid_until')->label('Berlaku sampai')->state(fn ($record) => $record->validityText()),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => AssetDocument::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AssetDocument::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('files_count')->label('Berkas')->formatStateUsing(fn ($state) => $state ?: 'belum'),
            ])
            ->emptyStateHeading('Belum ada dokumen digital')
            ->emptyStateDescription('Aset boleh tidak punya dokumen. Bila kelasnya mewajibkan dokumen, kekurangannya tampil di laporan "Aset belum lengkap dokumennya".')
            ->headerActions([
                Action::make('daftar')->label('Daftarkan dokumen')->icon('heroicon-o-document-plus')->visible($can)
                    ->schema(fn (Schema $schema) => $schema->components(AssetDocumentResource::cardFields(withAssets: false, withFiles: true)))
                    ->fillForm(['classification' => 'internal', 'pic_id' => auth()->id()])
                    ->action(function (array $data) use ($asset) {
                        \App\Filament\Support\F::attempt(fn () => app(DocumentService::class)->create(CreateAssetDocument::withFiles($data) + ['asset_ids' => [$asset->id]], auth()->user()), 'Dokumen terdaftar & ditautkan ke aset');
                    }),
                AttachAction::make()->label('Tautkan dokumen yang ada')->visible($can)->preloadRecordSelect(false)
                    ->recordSelectOptionsQuery(fn ($query) => $query->visibleTo(auth()->user())->whereIn('asset_documents.status', AssetDocument::LIVE))
                    ->recordSelectSearchColumns(['register_no', 'title', 'document_number']),
            ])
            ->recordActions([
                Action::make('buka')->label('Buka')->icon('heroicon-o-eye')->url(fn (AssetDocument $record) => AssetDocumentResource::getUrl('view', ['record' => $record])),
                DetachAction::make()->label('Lepas')->visible($can),
            ])
            ->defaultSort('valid_until');
    }

    protected function missingText(): ?HtmlString
    {
        $asset = $this->getOwnerRecord();
        $class = $asset->depreciationClass();
        if (! $class) {
            return null;
        }
        $have = $asset->documents()->whereIn('asset_documents.status', AssetDocument::LIVE)->pluck('document_type_id')->all();
        $missing = $class->requirements()->where('is_mandatory', true)->with('documentType')->get()
            ->reject(fn ($r) => in_array($r->document_type_id, $have, true))
            ->map(fn ($r) => e($r->documentType->code.' '.$r->documentType->name));

        return new HtmlString('Kelas '.e($class->code).': '.($missing->isEmpty()
            ? '<span style="color:var(--k-hijau)">dokumen wajib lengkap.</span>'
            : '<span style="color:var(--k-kuning)">belum ada '.$missing->join(', ').'.</span>'));
    }
}
