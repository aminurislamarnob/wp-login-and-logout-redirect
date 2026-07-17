### For dev. environment

Run the following command for development environment.

```
composer update
```

### For production environment
Run the following command for production environment to ignore the dev dependencies.

```
composer update --no-dev
```

### JavaScript / React admin settings

The admin settings page is a React app built with `@wordpress/scripts`. Install the Node dependencies, then build or watch the bundle (output goes to `assets/build/`).

```
# Install JS dependencies
npm install

# Build the admin bundle for production
npm run build

# Watch and rebuild during development
npm run start

# Lint and format
npm run lint:js
npm run format
```

### Build Release
Set execution permission to the script file by `chmod +x bin/build.sh` command. Now, Run the following bash script.
```
bin/build.sh
```