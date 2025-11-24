<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Models\Audit;
use UnitEnum;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'Gerenciamento';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Logs de Auditoria';

    protected static ?string $modelLabel = 'Log de Auditoria';

    protected static ?string $pluralModelLabel = 'Logs de Auditoria';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Usuário')
                    ->schema([
                        Placeholder::make('user')
                            ->label('Usuário')
                            ->content(function (Audit $record): string {
                                $user = $record->user;
                                if ($user === null || ! is_object($user)) {
                                    return 'Sistema';
                                }
                                $name = property_exists($user, 'name') && is_scalar($user->name) ? (string) $user->name : 'Unknown';
                                $email = property_exists($user, 'email') && is_scalar($user->email) ? (string) $user->email : 'unknown';

                                return $name.' ('.$email.')';
                            }),

                        TextInput::make('user_type')
                            ->label('Tipo de Usuário')
                            ->disabled(),

                        TextInput::make('user_id')
                            ->label('ID do Usuário')
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Informações da Ação')
                    ->schema([
                        TextInput::make('event')
                            ->label('Evento')
                            ->disabled(),

                        TextInput::make('auditable_type')
                            ->label('Tipo de Recurso')
                            ->disabled(),

                        TextInput::make('auditable_id')
                            ->label('ID do Recurso')
                            ->disabled(),

                        Placeholder::make('created_at')
                            ->label('Data/Hora')
                            ->content(function (Audit $record): string {
                                $createdAt = property_exists($record, 'created_at') ? $record->created_at : null;
                                if ($createdAt instanceof \Illuminate\Support\Carbon) {
                                    return $createdAt->format('d/m/Y H:i:s');
                                }

                                return '';
                            }),
                    ])
                    ->columns(4),

                Section::make('Alterações')
                    ->schema([
                        KeyValue::make('old_values')
                            ->label('Valores Anteriores')
                            ->disabled()
                            ->columnSpanFull(),

                        KeyValue::make('new_values')
                            ->label('Valores Novos')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Metadados')
                    ->schema([
                        TextInput::make('url')
                            ->label('URL')
                            ->disabled()
                            ->columnSpanFull(),

                        TextInput::make('ip_address')
                            ->label('Endereço IP')
                            ->disabled(),

                        TextInput::make('user_agent')
                            ->label('User Agent')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->default('Sistema')
                    ->description(function (Audit $record): ?string {
                        $user = $record->user;
                        if ($user === null || ! is_object($user)) {
                            return null;
                        }
                        $email = property_exists($user, 'email') ? $user->email : null;

                        return is_scalar($email) ? (string) $email : null;
                    }),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                        'restored' => 'Restaurado',
                        default => ucfirst($state),
                    }),

                TextColumn::make('auditable_type')
                    ->label('Tipo de Recurso')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('auditable_id')
                    ->label('ID do Recurso')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                        'restored' => 'Restaurado',
                    ]),

                SelectFilter::make('auditable_type')
                    ->label('Tipo de Recurso')
                    ->options([
                        'App\Models\User' => 'User',
                        'App\Models\Role' => 'Role',
                        'App\Models\Permission' => 'Permission',
                        'App\Models\Cota' => 'Cota',
                        'App\Models\CotaEspecial' => 'Cota Especial',
                        'App\Models\Lancamento' => 'Lançamento (Extrato)',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->options(fn () => \App\Models\User::pluck('name', 'id')->toArray())
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction(ViewAction::class)
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAudits::route('/'),
            'view' => Pages\ViewAudit::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
