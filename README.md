# Gitignore writer

This library can be used to programmatically add and remove items from a .gitignore file.
Allthough it would work on any type of file that considers the prefix # an comment line.

Managing entries works based on a section name, this way you can manage multiple 
sections separately. It is also used to find the section when adjusting it.

## Usage

```php
$gitIgnoreWriter = new \Zawadi\GitignoreWriter\GitignoreWriter(
    './path/.gitignore', # the path of your .gitignore file 
    'name-of-my-section' # the name of the section you want to edit
);
# add items
$gitIgnoreWriter->updateSection(['/robots.txt', '/admin']);

# remove items
$gitIgnoreWriter->updateSection([], ['/robots.txt', '/admin']);

# add and remove items at the same time
$gitIgnoreWriter->updateSection(['/robots.txt', '/admin'], ['to.remove.txt']);

# replace entire section with new items; all existing items will be removed
$gitIgnoreWriter->replaceSection(['/robots.txt', '/admin']);

# remove entire section is the same as replacing it with nothing
$gitIgnoreWriter->replaceSection();

# get list of current entries in a section
$entries = $gitIgnoreWriter->getEntries();
```

The output in the .gitignore file will look like this:

```gitignore
###> name-of-mysection >###
/admin
/robots.txt
###< name-of-mysection <###
```

Leave the comments around the entries, as those are used to find the section again 
when you need to update it again.

## Common questions answered

- When the file does not exist, it will be created.
- When a section does not exist, it will be appended to the end of the file.
- When a section is removed or when the last item is removed from a section, the 
  entire section will be removed from the file.
- When updating a section, but it would result in nothing changing, the file will not 
  be touched.
- Duplicate entries will be removed inside the section.
- Entries outside the section are ignored and not touched.
- Entries will be sorted alphabetically.
- Entries are added as-is.

### Newlines

Don't use newlines in entries, they will not be considered when writing the file, but 
will when reading the file.
