# Release Notes

## [Unreleased](https://github.com/laravel/installer/compare/v3.0.1...master)


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
