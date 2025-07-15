<?php

namespace App\Filament\Resources\UserResource\Pages;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
class EditProfile  extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([

                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                TextInput::make('chat_id')
                    ->label(__('Телеграм Чат ID'))
                    ->maxLength(255)
                    ->helperText(__('Укажите ваш Telegram Chat ID, если вы хотите получать уведомления в Telegram. Чтобы узнать ваш чат-идентификатор, посетите @myidbot
Если вы отправите команду /getid, она отправит вам ваш чат-идентификатор.')),
            ]);
    }
}