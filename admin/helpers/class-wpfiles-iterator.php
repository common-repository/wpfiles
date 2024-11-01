<?php

/**
 * Class Iterator extends RecursiveFilterIterator
 * This is actually used for reading directories recursively
 */
class Wp_Files_Iterator extends RecursiveFilterIterator
{
    /**
     * Accept method.
     * @since 1.0.0
     * @return bool
     */
    public function accept(): bool
    {
        $path = $this->current()->getPathname();

        $dir = new Wp_Files_Directory();

        if (!$this->isDir()) {
            return true;
        }

        if (!$dir->skipDirectory($path) && $this->isDir()) {
            return true;
        }

        return false;
    }
}
