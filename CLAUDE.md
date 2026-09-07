# Проектные инструкции — garnet-framework

## Выпуск альфы

- Выпуская новый alpha-тег, **всегда удаляй предыдущий** — и локально,
  и на origin. На Packagist должен оставаться только актуальный тег:
  ```
  git tag -d v0.1.0-alpha<N-1>
  git push origin :refs/tags/v0.1.0-alpha<N-1>
  git tag v0.1.0-alpha<N>
  git push origin master --tags
  ```
- Следствие, о котором надо помнить: `composer.lock` приложения после
  этого какое-то время указывает на тег, которого на origin уже нет.
  Поэтому сразу за выпуском обновляй приложение
  (`composer update phpcraftdream/garnet-framework`) — иначе чистый
  `composer install` встанет на пропавшей ссылке.

- Перед КАЖДЫМ пушем в этот репозиторий сам прогоняешь локальные проверки качества (`composer cs:check`, `composer phpstan`, `composer test:kernel`, `composer test:bundle`) и пушишь только при их зелёном результате. Не полагайся на удалённый CI как на первую линию проверки — GitHub Actions обрезает список аннотаций/warning'ов (видно не более ~10 на джоб) и может создать ложное ощущение, что всё чисто.
