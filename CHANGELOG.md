# Release Notes

## [Unreleased](https://github.com/laravel/installer/compare/v4.1.1...master)


## [v4.1.1 (2020-11-17)](https://github.com/laravel/installer/compare/v4.0.7...v4.1.1)

### Changed
- Require name argument ([#178](https://github.com/laravel/installer/pull/178))


## [v4.1.0 (2020-11-03)](https://github.com/laravel/installer/compare/v4.0.7...v4.1.0)

### Added
- PHP 8 Support ([#168](https://github.com/laravel/installer/pull/168))

### Changed
- Use `dev-master` for `dev` version ([9ce64f82](https://github.com/laravel/installer/commit/9ce64f82dcc6d700d91e34b7bcfc32f0b16e2839))


## [v4.0.7 (2020-10-30)](https://github.com/laravel/installer/compare/v4.0.6...v4.0.7)

### Fixed
- Fixed some jetstream prompt issues


## [v4.0.6 (2020-10-30)](https://github.com/laravel/installer/compare/v4.0.5...v4.0.6)

### Added
- Add prompt-jetstream switch ([95c3a00](https://github.com/laravel/installer/commit/95c3a00ee7fc188121ae3e90292f712eae19b26b))

### Changed
- Update `DB_DATABASE` in `.env.example` ([#167](https://github.com/laravel/installer/pull/167))


## [v4.0.5 (2020-09-22)](https://github.com/laravel/installer/compare/v4.0.4...v4.0.5)

### Fixed
- Ensure artisan command is executable ([#153](https://github.com/laravel/installer/pull/153))
- Fix quiet and no-ansi flags ([#156](https://github.com/laravel/installer/pull/156))


## [v4.0.4 (2020-09-15)](https://github.com/laravel/installer/compare/v4.0.3...v4.0.4)

### Fixed
- Close `<fg>` tag ([#149](https://github.com/laravel/installer/pull/149))
- Add warning about `--force` and installing in current directory ([#152](https://github.com/laravel/installer/pull/152))


## [v4.0.3 (2020-09-08)](https://github.com/laravel/installer/compare/v4.0.2...v4.0.3)

### Fixed
- Fix for directories with spaces in current working directory path ([#147](https://github.com/laravel/installer/pull/147))


## [v4.0.2 (2020-09-08)](https://github.com/laravel/installer/compare/v4.0.1...v4.0.2)

### Added
- Add stack and teams options ([#143](https://github.com/laravel/installer/pull/143))


## [v4.0.1 (2020-09-07)](https://github.com/laravel/installer/compare/v4.0.0...v4.0.1)

### Changed
- Require PHP 7.3 ([#132](https://github.com/laravel/installer/pull/132))

### Fixed
- Fix multiple issues when running on Windows ([#133](https://github.com/laravel/installer/pull/133), [#137](https://github.com/laravel/installer/pull/137))
- Only change `.env` file when project name exists ([#140](https://github.com/laravel/installer/pull/140))


## [v4.0.0 (2020-09-03)](https://github.com/laravel/installer/compare/v3.2.0...v4.0.0)

### Changed
- Switch to `composer create-project` ([#124](https://github.com/laravel/installer/pull/124), [562650d](https://github.com/laravel/installer/commit/562650de8b637253b7ae47c3383bdd20e8419d1c), [8ab3502](https://github.com/laravel/installer/commit/8ab3502f1d5561d10cf1767213ec0c008baa145b))


## [v3.2.0 (2020-06-30)](https://github.com/laravel/installer/compare/v3.1.0...v3.2.0)

### Added
- Guzzle 7 support ([144a695](https://github.com/laravel/installer/commit/144a69576bfb0df2bbd5c7ae3f40dd87db64d0ba))


## [v3.1.0 (2020-05-21)](https://github.com/laravel/installer/compare/v3.0.1...v3.1.0)

### Removed
- Drop support for PHP 7.2 ([#118](https://github.com/laravel/installer/pull/118))


## [v3.0.1 (2019-11-26)](https://github.com/laravel/installer/compare/v3.0.0...v3.0.1)

### Fixed
- Fix composer autoloader path ([f3db3f3](https://github.com/laravel/installer/commit/f3db3f306c3c2dbbf4ecce4a5dbefe6c1fd178be))


## [v3.0.0 (2019-11-26)](https://github.com/laravel/installer/compare/v2.3.0...v3.0.0)

### Changed
- Move `laravel` binary to new directory ([c581a78](https://github.com/laravel/installer/commit/c581a784643911b97c3b8a2ec25ac809eadbf9c5))
- Require PHP 7.2 as the new minimum version ([3ab97f2](https://github.com/laravel/installer/commit/3ab97f2e454d9c95833ccdd141d2fdbcdc8e0066))
- Allow Symfony 5 ([513a060](https://github.com/laravel/installer/commit/513a060e9877bc8ab222d7ff4a60bc97131a0a0c))

### Removed
- Remove Symfony 3.x support ([a09d8fe](https://github.com/laravel/installer/commit/a09d8fe2ced9579d4fce445aa1336b0993e3e9d0))
- Remove `zipper.sh` ([78ef1db](https://github.com/laravel/installer/commit/78ef1dbe9ad2fbe5f16a85917748f89bb372599f))


## [v2.3.0 (2019-11-19)](https://github.com/laravel/installer/compare/v2.2.1...v2.3.0)

### Added
- Add `--auth` flag ([f5ebbff](https://github.com/laravel/installer/commit/f5ebbff32f9ff9c40fdf4c200cb2f396050e3cf3))


## [v2.2.1 (2019-10-29)](https://github.com/laravel/installer/compare/v2.2.0...v2.2.1)

### Fixed
- Make sure zip file is valid before extracting ([#100](https://github.com/laravel/installer/pull/100))


## [v2.2.0 (2019-10-15)](https://github.com/laravel/installer/compare/v2.1.0...v2.2.0)

### Added
- Create a new project in the current directory using "laravel new ." ([#99](https://github.com/laravel/installer/pull/99))


## [v2.1.0 (2019-04-30)](https://github.com/laravel/installer/compare/v2.0.1...v2.1.0)

### Added
- Added an alias to the `--force` option ([#79](https://github.com/laravel/installer/pull/79))

### Changed
- Use the `extension_loaded` method to check if the 'zip' extension is loaded ([#81](https://github.com/laravel/installer/pull/81))

### Fixed
- Respect `--quiet` option ([#77](https://github.com/laravel/installer/pull/77))
- Update composer path on `findComposer` ([#86](https://github.com/laravel/installer/pull/86))


## [v2.0.1 (2018-02-01)](https://github.com/laravel/installer/compare/v2.0.0...v2.0.1)

### Changed
- Update dependencies ([6e34188](https://github.com/laravel/installer/commit/6e341883b9ba45be6a06f40c8e2c1b5033029d99))


## [v2.0.0 (2018-02-01)](https://github.com/laravel/installer/compare/v1.5.0...v2.0.0)

### Changed
- Bump guzzle requirement ([f909b98](https://github.com/laravel/installer/commit/f909b983e1b57f13b5b102f4c0c0fc1883fcbe22))
