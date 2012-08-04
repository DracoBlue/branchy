# branchy

* Version: 1.0.0
* Date: 4. August 2012

**branchy** is a subversion tool to control and manage branches in a feature driven way. Similar to gitflow by nvie <https://github.com/nvie/gitflow>. **branchy** is copyright 2011-2012 by DracoBlue.

## Example Usage

### Start the Feature Branch

Create a new feature branch `awesome_extension`

    $ branchy start-feature awesome_extension

The tool will create a branch called `/branches/awesome_extension`.

It will also switch the local copy to the branch.

### Continue developing

Now you can commit into that branch.

### Getting latest changes from `trunk`

If you want to merge latest changes from branch, do it with this little wrapper (around svn merge):

    $ branchy merge-trunk

After this merge, you have to `svn commit` the changes! If you want to automaticlly commit the changes, do it with the auto-merge-trunk option:

    $ branchy auto-merge-trunk

### Show changes

If you want to see what changed in your branch, you can show the changes against the `trunk` with

    $ branchy show-changes
    
### Finish the Feature Branch

If you finished your work, you can finish the feature with this

    $ branchy finish-feature

This will merge all changes back into the `/trunk` and delete the branch. 

## Additional Features

### Checkout features

If you want to checkout a specific feature, you can list all available features with:

    $ branchy features

and checkout a specific feature with:

    $ branchy checkout awesome_feature

If you want to checkout the trunk you can do it like this:

    $ branchy cehckout trunk

### Show changes between 2 features

If you want to see what changed in your branch, you can show the changes against another feature `awesome2` with

    $ branchy show-changes awesome2
    

### .bashrc autocompletion

If you want to have autocompletion for feature names for branchy checkout and branchy show-changes, you have to add those lines to your .bashrc file.

    _branchy_magic() 
    {
        local cur prev opts
        COMPREPLY=()
        cur="${COMP_WORDS[COMP_CWORD]}" 
    
        if [ "$COMP_CWORD" == "1" ]
        then
            opts=$( branchy help 2>&1 | grep "^ \w" | cut -f '2-' -d ' ' | grep ^$cur )
            COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
        fi
    
        if [ "$COMP_CWORD" == "2" ]
        then
            action_name="${COMP_WORDS[1]}" 
    
            if [ "$action_name" == "show-changes" ]
            then
                opts=$( branchy features 2>&1 | grep ']' | cut -f '2-' -d ']' | cut -f '2-' -d ' ' | grep ^$cur )
                COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
            fi
            if [ "$action_name" == "checkout" ]
            then
                opts=$( branchy features 2>&1 | grep ']' | cut -f '2-' -d ']' | cut -f '2-' -d ' ' | grep ^$cur )
                COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
            fi
        fi
    
    
        return 0
    }
    
    complete -F _branchy_magic branchy

## Resources

* Tickets: https://github.com/DracoBlue/branchy/issues

## Changelog

* 1.0.0 (2012/08/04)
 - added possibility to use show-changes with additional feature name
 - added help action to display information about all available actions
 - added .bashrc snippet 
 - initial version

## License

This work is copyright by DracoBlue (<http://dracoblue.net>) and licensed under the terms of MIT License.
