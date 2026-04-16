; Inno Setup script — Don David POS (standalone Windows installer)
; Compile with: "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" DonDavid.iss
; (or use build-installer.ps1 which pins paths + fetches the payload)

#define AppName        "Don David POS"
#define AppVersion     "1.0.0"
#define AppPublisher   "COLDEVS"
#define DefaultRoot    "C:\DonDavid"

[Setup]
AppId={{B1E4D1F4-DON1-DAVI-POS0-000000000001}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={#DefaultRoot}
DisableDirPage=no
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
OutputDir=output
OutputBaseFilename=DonDavidSetup-{#AppVersion}
Compression=lzma2/ultra
SolidCompression=yes
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
PrivilegesRequired=admin
WizardStyle=modern
SetupIconFile=payload\DonDavid.ico

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Files]
; App code (repo copy — vendor\ must be pre-installed by build-installer.ps1)
Source: "payload\app\*"; DestDir: "{app}\app"; Flags: recursesubdirs createallsubdirs ignoreversion
; Portable PHP
Source: "payload\php\*"; DestDir: "{app}\php"; Flags: recursesubdirs createallsubdirs ignoreversion
; Portable Postgres
Source: "payload\pgsql\*"; DestDir: "{app}\pgsql"; Flags: recursesubdirs createallsubdirs ignoreversion
; Composer (used if vendor wasn't shipped, or for upgrades)
Source: "payload\composer.phar"; DestDir: "{app}"; Flags: ignoreversion
; Launcher scripts
Source: "scripts\*.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion
; Icon
Source: "payload\DonDavid.ico"; DestDir: "{app}"; Flags: ignoreversion

[Dirs]
Name: "{app}\logs"
Name: "{app}\run"

[Code]
var
  CustomPage: TInputQueryWizardPage;
  PasswordPage: TInputQueryWizardPage;
  StartupPage: TInputOptionWizardPage;

procedure InitializeWizard;
begin
  CustomPage := CreateInputQueryPage(
    wpSelectDir,
    'Configuracion',
    'Parametros de la instalacion',
    'Complete los siguientes campos. El instalador usara estos valores para configurar la base de datos, la impresora y el usuario administrador.');
  CustomPage.Add('Puerto HTTP (ej. 8000):', False);
  CustomPage.Add('Nombre de la cola de impresion (ej. XP-80C):', False);
  CustomPage.Add('Email del administrador:', False);
  CustomPage.Values[0] := '8000';
  CustomPage.Values[1] := 'XP-80C';
  CustomPage.Values[2] := 'admin@dondavid.local';

  PasswordPage := CreateInputQueryPage(
    CustomPage.ID,
    'Contrasena del administrador',
    'Cree la contrasena para el primer usuario administrador',
    'Escriba la contrasena dos veces. Podra cambiarla luego desde la aplicacion.');
  PasswordPage.Add('Contrasena:', True);
  PasswordPage.Add('Confirmar:', True);

  StartupPage := CreateInputOptionPage(
    PasswordPage.ID,
    'Inicio automatico',
    'Iniciar Don David POS con Windows',
    'Puede registrar el acceso directo en el arranque de Windows para que el sistema quede listo al encender el PC.',
    False, False);
  StartupPage.Add('Si, iniciar automaticamente al encender (recomendado en PCs de caja).');
  StartupPage.Values[0] := True;
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  p: string;
begin
  Result := True;
  if CurPageID = CustomPage.ID then begin
    if (CustomPage.Values[0] = '') or (CustomPage.Values[1] = '') or (CustomPage.Values[2] = '') then begin
      MsgBox('Todos los campos son obligatorios.', mbError, MB_OK);
      Result := False;
    end;
  end;
  if CurPageID = PasswordPage.ID then begin
    p := PasswordPage.Values[0];
    if (Length(p) < 6) then begin
      MsgBox('La contrasena debe tener al menos 6 caracteres.', mbError, MB_OK);
      Result := False;
    end else if (p <> PasswordPage.Values[1]) then begin
      MsgBox('Las contrasenas no coinciden.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function GetInstallArgs(Param: string): string;
var
  args: string;
begin
  args :=
    ' -InstallRoot "'   + ExpandConstant('{app}') + '"' +
    ' -Port '           + CustomPage.Values[0] +
    ' -PrinterQueue "'  + CustomPage.Values[1] + '"' +
    ' -AdminEmail "'    + CustomPage.Values[2] + '"' +
    ' -AdminPassword "' + PasswordPage.Values[0] + '"';
  if StartupPage.Values[0] then
    args := args + ' -CreateStartupShortcut';
  Result := args;
end;

[Run]
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\install.ps1""{code:GetInstallArgs}"; \
  StatusMsg: "Configurando la aplicacion..."; \
  Flags: runhidden waituntilterminated

[UninstallRun]
; Stop services + processes before file removal
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\scripts\stop.ps1"""; Flags: runhidden; RunOnceId: "stopProcs"
Filename: "sc.exe"; Parameters: "stop DonDavidPostgres"; Flags: runhidden; RunOnceId: "stopPg"
Filename: "{app}\pgsql\bin\pg_ctl.exe"; Parameters: "unregister -N DonDavidPostgres"; Flags: runhidden; RunOnceId: "unregPg"
; Firewall cleanup (remove all rules with our prefix)
Filename: "powershell.exe"; Parameters: "-NoProfile -Command ""Get-NetFirewallRule -DisplayName 'Don David POS*' | Remove-NetFirewallRule"""; Flags: runhidden; RunOnceId: "fw"
; Desktop + startup shortcut cleanup
Filename: "cmd.exe"; Parameters: "/c del ""%PUBLIC%\Desktop\Don David POS.lnk"" ""%ALLUSERSPROFILE%\Microsoft\Windows\Start Menu\Programs\StartUp\Don David POS.lnk"""; Flags: runhidden; RunOnceId: "shortcuts"
