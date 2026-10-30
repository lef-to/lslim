# LSlim

Utility files for Slim framework version 4.

## 開発環境

Docker と Docker Compose、および VS Code の Dev Containers 拡張機能を使用。

### PHP 拡張

- `mbstring`
- `intl`
- `bcmath`
- `pdo_mysql`
- `pdo_sqlite`
- `pcntl`
- `zip`
- `redis`

※`fileinfo`、`iconv`、DOM/XML 関連の拡張はベースイメージに含む。

### 接続先

| サービス | 接続先 | 設定 |
| --- | --- | --- |
| MySQL | `mysql:3306` | DB: `lslim`、ユーザー: `lslim`、パスワード: `lslim` |
| テスト用 DB | `mysql:3306` | DB: `lslim_test`、ユーザー・パスワードは同じ |
| Redis | `redis:6379` | 認証なし |
| メール SMTP | `mailpit:1025` | DSN: `smtp://mailpit:1025` |
| メール確認画面 | VS Code の Ports から `mailpit:8025` を開く | Mailpit Web UI |

`DB_*`、`REDIS_*`、`MAILER_DSN` の環境変数を開発コンテナに設定。

DB を初期状態に戻す場合は、必要なデータを退避してから `docker compose down --volumes` を実行してDB のデータを削除する。

### コンテナ内での確認

```sh
php -v
composer --version
composer check-platform-reqs
```

### テスト

PHPUnit 12 を使用。

```sh
composer install
composer test              # 単体テストと結合テストのすべて
composer test:unit         # 外部サービス不要の単体テスト
composer test:integration  # MySQL・Redis・Mailpit を使う結合テスト
```

ホストからは `docker compose exec app composer test` で実行。
個別に実行する場合は `composer test -- --filter CsvReaderTest` のように指定する。
