<?php

namespace Charcoal\Property;

use finfo;
use PDO;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

// From Pimple
use Pimple\Container;

// From 'charcoal-translator'
use Charcoal\Translator\Translation;

// From 'charcoal-property'
use Charcoal\Property\AbstractProperty;

/**
 * File Property
 */
class FileProperty extends AbstractProperty
{
    const DEFAULT_PUBLIC_ACCESS = false;
    const DEFAULT_UPLOAD_PATH = 'uploads/';
    const DEFAULT_FILESYSTEM = 'public';
    const DEFAULT_OVERWRITE = false;
    const ERROR_MESSAGES = [
        UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success',
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive'.
                                 'that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];

    /**
     * Whether uploaded files should be accessible from the web root.
     *
     * @var boolean
     */
    private $publicAccess = self::DEFAULT_PUBLIC_ACCESS;

    /**
     * The relative path to the storage directory.
     *
     * @var string
     */
    private $uploadPath = self::DEFAULT_UPLOAD_PATH;

    /**
     * The base path for the Charcoal installation.
     *
     * @var string
     */
    private $basePath;

    /**
     * The path to the public / web directory.
     *
     * @var string
     */
    private $publicPath;

    /**
     * Whether existing destinations should be overwritten.
     *
     * @var boolean
     */
    private $overwrite = self::DEFAULT_OVERWRITE;

    /**
     * Collection of accepted MIME types.
     *
     * @var string[]
     */
    private $acceptedMimetypes = [];

    /**
     * Current file mimetype
     *
     * @var string
     */
    private $mimetype;

    /**
     * Maximum allowed file size, in bytes.
     *
     * @var integer
     */
    private $maxFilesize;

    /**
     * Current file size, in bytes.
     *
     * @var integer
     */
    private $filesize;

    /**
     * The filesystem to use while uploading a file.
     *
     * @var string
     */
    private $filesystem = self::DEFAULT_FILESYSTEM;

    /**
     * Holds a list of all normalized paths.
     *
     * @var string[]
     */
    protected static $normalizePathCache = [];

    /**
     * @return string
     */
    public function type()
    {
        return 'file';
    }

    /**
     * Set whether uploaded files should be publicly available.
     *
     * @param  boolean $public Whether uploaded files should be accessible (TRUE) or not (FALSE) from the web root.
     * @return self
     */
    public function setPublicAccess($public)
    {
        $this->publicAccess = !!$public;

        return $this;
    }

    /**
     * Determine if uploaded files should be publicly available.
     *
     * @return boolean
     */
    public function getPublicAccess()
    {
        return $this->publicAccess;
    }

    /**
     * Set the destination (directory) where uploaded files are stored.
     *
     * The path must be relative to the {@see self::basePath()},
     *
     * @param  string $path The destination directory, relative to project's root.
     * @throws InvalidArgumentException If the path is not a string.
     * @return self
     */
    public function setUploadPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException(
                'Upload path must be a string'
            );
        }

        // Sanitize upload path (force trailing slash)
        $this->uploadPath = rtrim($path, '/').'/';

        return $this;
    }

    /**
     * Retrieve the destination for the uploaded file(s).
     *
     * @return string
     */
    public function getUploadPath()
    {
        return $this->uploadPath;
    }

    /**
     * Set whether existing destinations should be overwritten.
     *
     * @param  boolean $overwrite Whether existing destinations should be overwritten (TRUE) or not (FALSE).
     * @return self
     */
    public function setOverwrite($overwrite)
    {
        $this->overwrite = !!$overwrite;

        return $this;
    }

    /**
     * Determine if existing destinations should be overwritten.
     *
     * @return boolean
     */
    public function getOverwrite()
    {
        return $this->overwrite;
    }

    /**
     * @param  string[] $mimetypes The accepted mimetypes.
     * @return self
     */
    public function setAcceptedMimetypes(array $mimetypes)
    {
        $this->acceptedMimetypes = $mimetypes;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAcceptedMimetypes()
    {
        return $this->acceptedMimetypes;
    }

    /**
     * Set the MIME type.
     *
     * @param  mixed $type The file MIME type.
     * @throws InvalidArgumentException If the MIME type argument is not a string.
     * @return FileProperty Chainable
     */
    public function setMimetype($type)
    {
        if ($type === null || $type === false) {
            $this->mimetype = null;

            return $this;
        }

        if (!is_string($type)) {
            throw new InvalidArgumentException(
                'Mimetype must be a string'
            );
        }

        $this->mimetype = $type;

        return $this;
    }

    /**
     * Retrieve the MIME type.
     *
     * @return string
     */
    public function getMimetype()
    {
        if (!$this->mimetype) {
            $val = $this->val();

            if (!$val) {
                return '';
            }

            $this->setMimetype($this->getMimetypeFor(strval($val)));
        }

        return $this->mimetype;
    }

    /**
     * Alias of {@see self::getMimetype()}.
     *
     * @return string
     */
    public function mimetype()
    {
        return $this->getMimetype();
    }

    /**
     * Extract the MIME type from the given file.
     *
     * @uses   finfo
     * @param  string $file The file to check.
     * @return string|null Returns the given file's MIME type or FALSE if an error occurred.
     */
    public function getMimetypeFor($file)
    {
        if (!$this->fileExists($file)) {
            return null;
        }

        $info = new finfo(FILEINFO_MIME_TYPE);

        return $info->file($file);
    }

    /**
     * Alias of {@see self::getMimetypeFor()}.
     *
     * @param  string $file The file to check.
     * @return string|false
     */
    public function mimetypeFor($file)
    {
        return $this->getMimetypeFor($file);
    }

    /**
     * Set the maximium size accepted for an uploaded files.
     *
     * @param  string|integer $size The maximum file size allowed, in bytes.
     * @throws InvalidArgumentException If the size argument is not an integer.
     * @return FileProperty Chainable
     */
    public function setMaxFilesize($size)
    {
        $this->maxFilesize = $this->parseIniSize($size);

        return $this;
    }

    /**
     * Retrieve the maximum size accepted for uploaded files.
     *
     * If null or 0, then no limit. Defaults to 128 MB.
     *
     * @return integer
     */
    public function getMaxFilesize()
    {
        if (!isset($this->maxFilesize)) {
            return $this->maxFilesizeAllowedByPhp();
        }

        return $this->maxFilesize;
    }

    /**
     * Retrieve the maximum size (in bytes) allowed for an uploaded file
     * as configured in {@link http://php.net/manual/en/ini.php `php.ini`}.
     *
     * @param string|null $iniDirective If $iniDirective is provided, then it is filled with
     *     the name of the PHP INI directive corresponding to the maximum size allowed.
     * @return integer
     */
    public function maxFilesizeAllowedByPhp(&$iniDirective = null)
    {
        $postMaxSize = $this->parseIniSize(ini_get('post_max_size'));
        $uploadMaxFilesize = $this->parseIniSize(ini_get('upload_max_filesize'));

        if ($postMaxSize < $uploadMaxFilesize) {
            $iniDirective = 'post_max_size';

            return $postMaxSize;
        } else {
            $iniDirective = 'upload_max_filesize';

            return $uploadMaxFilesize;
        }
    }

    /**
     * @param  integer $size The file size, in bytes.
     * @throws InvalidArgumentException If the size argument is not an integer.
     * @return FileProperty Chainable
     */
    public function setFilesize($size)
    {
        if (!is_int($size)) {
            throw new InvalidArgumentException(
                'Filesize must be an integer, in bytes.'
            );
        }
        $this->filesize = $size;

        return $this;
    }

    /**
     * @return integer
     */
    public function getFilesize()
    {
        if (!$this->filesize) {
            $val = $this->val();
            if (!$val || !$this->fileExists($val)) {
                return 0;
            } else {
                $this->filesize = filesize($val);
            }
        }

        return $this->filesize;
    }

    /**
     * Alias of {@see self::getFilesize()}.
     *
     * @return integer
     */
    public function filesize()
    {
        return $this->getFilesize();
    }

    /**
     * @return array
     */
    public function validationMethods()
    {
        $parentMethods = parent::validationMethods();

        return array_merge($parentMethods, [
            'acceptedMimetypes',
            'maxFilesize',
        ]);
    }

    /**
     * @return boolean
     */
    public function validateAcceptedMimetypes()
    {
        $acceptedMimetypes = $this['acceptedMimetypes'];
        if (empty($acceptedMimetypes)) {
            // No validation rules = always true
            return true;
        }

        if ($this->mimetype) {
            $mimetype = $this->mimetype;
        } else {
            $val = $this->val();
            if (!$val || !$this->fileExists($val)) {
                return true;
            }
            $mimetype = $this->getMimetypeFor($val);
        }
        $valid = false;
        foreach ($acceptedMimetypes as $m) {
            if ($m === $mimetype) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $this->validator()->error('Accepted mimetypes error', 'acceptedMimetypes');
        }

        return $valid;
    }

    /**
     * @return boolean
     */
    public function validateMaxFilesize()
    {
        $maxFilesize = $this['maxFilesize'];
        if ($maxFilesize == 0) {
            // No max size rule = always true
            return true;
        }

        $filesize = $this->filesize();
        $valid = ($filesize <= $maxFilesize);
        if (!$valid) {
            $this->validator()->error('Max filesize error', 'maxFilesize');
        }

        return $valid;
    }

    /**
     * Get the SQL type (Storage format)
     *
     * Stored as `VARCHAR` for max_length under 255 and `TEXT` for other, longer strings
     *
     * @see StorablePropertyTrait::sqlType()
     * @return string The SQL type
     */
    public function sqlType()
    {
        // Multiple strings are always stored as TEXT because they can hold multiple values
        if ($this['multiple']) {
            return 'TEXT';
        } else {
            return 'VARCHAR(255)';
        }
    }

    /**
     * @see StorablePropertyTrait::sqlPdoType()
     * @return integer
     */
    public function sqlPdoType()
    {
        return PDO::PARAM_STR;
    }

    /**
     * Process file uploads {@see AbstractProperty::save() parsing values}.
     *
     * @param  mixed $val The value, at time of saving.
     * @return mixed
     */
    public function save($val)
    {
        if ($val instanceof Translation) {
            $values = $val->data();
        } else {
            $values = $val;
        }

        $uploadedFiles = $this->getUploadedFiles();

        if ($this['l10n']) {
            foreach ($this->translator()->availableLocales() as $lang) {
                if (!isset($values[$lang])) {
                    $values[$lang] = $this['multiple'] ? [] : '';
                }

                $parsedFiles = [];

                if (isset($uploadedFiles[$lang])) {
                    $parsedFiles = $this->saveFileUploads($uploadedFiles[$lang]);
                }

                if (empty($parsedFiles)) {
                    $parsedFiles = $this->saveDataUploads($values[$lang]);
                }

                $values[$lang] = $this->parseSavedValues($parsedFiles, $values[$lang]);
            }
        } else {
            $parsedFiles = [];

            if (!empty($uploadedFiles)) {
                $parsedFiles = $this->saveFileUploads($uploadedFiles);
            }

            if (empty($parsedFiles)) {
                $parsedFiles = $this->saveDataUploads($values);
            }

            $values = $this->parseSavedValues($parsedFiles, $values);
        }

        return $values;
    }

    /**
     * Process and transfer any data URIs to the filesystem,
     * and carry over any pre-processed file paths.
     *
     * @param  mixed $values One or more data URIs, data entries, or processed file paths.
     * @return string|string[] One or more paths to the processed uploaded files.
     */
    protected function saveDataUploads($values)
    {
        // Bag value if singular
        if (!is_array($values) || isset($values['id'])) {
            $values = [ $values ];
        }

        $parsed = [];
        foreach ($values as $value) {
            if ($this->isDataArr($value) || $this->isDataUri($value)) {
                $path = $this->dataUpload($value);
                if ($path !== null) {
                    $parsed[] = $path;
                }
            } elseif (is_string($value) && !empty($value)) {
                $parsed[] = $value;
            }
        }

        return $parsed;
    }

    /**
     * Process and transfer any uploaded files to the filesystem.
     *
     * @param  mixed $files One or more normalized $_FILE entries.
     * @return string[] One or more paths to the processed uploaded files.
     */
    protected function saveFileUploads($files)
    {
        // Bag value if singular
        if (isset($files['error'])) {
            $files = [ $files ];
        }

        $parsed = [];
        foreach ($files as $file) {
            if (isset($file['error'])) {
                $path = $this->fileUpload($file);
                if ($path !== null) {
                    $parsed[] = $path;
                }
            }
        }

        return $parsed;
    }

    /**
     * Finalize any processed files.
     *
     * @param  mixed $saved   One or more values, at time of saving.
     * @param  mixed $default The default value to return.
     * @return string|string[] One or more paths to the processed uploaded files.
     */
    protected function parseSavedValues($saved, $default = null)
    {
        $values = empty($saved) ? $default : $saved;

        if ($this['multiple']) {
            if (!is_array($values)) {
                $values = empty($values) && !is_numeric($values) ? [] : [ $values ];
            }
        } else {
            if (is_array($values)) {
                $values = reset($values);
            }
        }

        return $values;
    }

    /**
     * Upload to filesystem, from data URI.
     *
     * @param  mixed $data A data URI.
     * @throws Exception If data content decoding fails.
     * @throws InvalidArgumentException If the $data is invalid.
     * @return string|null The file path to the uploaded data.
     */
    public function dataUpload($data)
    {
        $filename = null;
        $content  = false;

        if (is_array($data)) {
            if (!isset($data['id'], $data['name'])) {
                throw new InvalidArgumentException(
                    '$data as an array MUST contain each of the keys "id" and "name", '.
                    'with each represented as a scalar value; one or more were missing or non-array values'
                );
            }
            // retrieve tmp file from temp dir
            $tmpDir  = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            $tmpFile = $tmpDir.$data['id'];
            if (!file_exists($tmpFile)) {
                throw new Exception(sprintf(
                    'File %s does not exists',
                    $data['id']
                ));
            }

            $content  = file_get_contents($tmpFile);
            $filename = empty($data['name']) ? null : $data['name'];

            // delete tmp file
            unlink($tmpFile);
        } elseif (is_string($data)) {
            $content = file_get_contents($data);
        }

        if ($content === false) {
            throw new Exception(
                'File content could not be decoded'
            );
        }

        $info = new finfo(FILEINFO_MIME_TYPE);
        $this->setMimetype($info->buffer($content));
        $this->setFilesize(strlen($content));
        if (!$this->validateAcceptedMimetypes() || !$this->validateMaxFilesize()) {
            return null;
        }

        $targetPath = $this->uploadTarget($filename);

        $result = file_put_contents($targetPath, $content);
        if ($result === false) {
            $this->logger->warning(sprintf(
                'Failed to write file to %s',
                $targetPath
            ));
            return null;
        }

        $basePath  = $this->basePath();
        $targetPath = str_replace($basePath, '', $targetPath);

        return $targetPath;
    }

    /**
     * Upload to filesystem.
     *
     * @link https://github.com/slimphp/Slim/blob/3.12.1/Slim/Http/UploadedFile.php
     *     Adapted from slim/slim.
     *
     * @param  array $file A single $_FILES entry.
     * @throws InvalidArgumentException If the $file is invalid.
     * @return string|null The file path to the uploaded file.
     */
    public function fileUpload(array $file)
    {
        if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error'])) {
            throw new InvalidArgumentException(
                '$file MUST contain each of the keys "tmp_name", "name", "size", and "error", '.
                'with each represented as a scalar value; one or more were missing or non-array values'
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->logger->warning(sprintf(
                'Upload error on file %s: %s',
                $file['name'],
                self::ERROR_MESSAGES[$this->error]
            ));

            return null;
        }

        if (file_exists($file['tmp_name'])) {
            $info = new finfo(FILEINFO_MIME_TYPE);
            $this->setMimetype($info->file($file['tmp_name']));
            $this->setFilesize(filesize($file['tmp_name']));
            if (!$this->validateAcceptedMimetypes() || !$this->validateMaxFilesize()) {
                return null;
            }
        } else {
            $this->logger->warning(sprintf(
                'File %s does not exists',
                $file['tmp_name']
            ));
            return null;
        }

        $targetPath = $this->uploadTarget($file['name']);

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->logger->warning(sprintf(
                '%s is not a valid uploaded file',
                $file['tmp_name']
            ));
            return null;
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $this->logger->warning(sprintf(
                'Error moving uploaded file %s to %s',
                $file['tmp_name'],
                $targetPath
            ));
            return null;
        }

        $this->logger->notice(sprintf(
            'File %s uploaded succesfully',
            $targetPath
        ));

        $basePath   = $this->basePath();
        $targetPath = str_replace($basePath, '', $targetPath);

        return $targetPath;
    }

    /**
     * @param string $filename Optional. The filename to save. If unset, a default filename will be generated.
     * @throws Exception If the target path is not writeable.
     * @return string
     */
    public function uploadTarget($filename = null)
    {
        $uploadPath = $this->basePath().$this['uploadPath'];

        if (!file_exists($uploadPath)) {
            // @todo: Feedback
            $this->logger->debug(
                'Path does not exist. Attempting to create path '.$uploadPath.'.',
                [ get_called_class().'::'.__FUNCTION__ ]
            );
            mkdir($uploadPath, 0777, true);
        }

        if (!is_writable($uploadPath)) {
            throw new Exception(
                'Error: upload directory is not writeable'
            );
        }

        $filename   = empty($filename) ? $this->generateFilename() : $this->sanitizeFilename($filename);
        $targetPath = $uploadPath.$filename;

        if ($this->fileExists($targetPath)) {
            if ($this['overwrite'] === true) {
                return $targetPath;
            } else {
                $targetPath = $uploadPath.$this->generateUniqueFilename($filename);
                while ($this->fileExists($targetPath)) {
                    $targetPath = $uploadPath.$this->generateUniqueFilename($filename);
                }
            }
        }

        return $targetPath;
    }

    /**
     * Checks whether a file or directory exists.
     *
     * PHP built-in's `file_exists` is only case-insensitive on case-insensitive filesystem (such as Windows)
     * This method allows to have the same validation across different platforms / filesystem.
     *
     * @param  string  $file            The full file to check.
     * @param  boolean $caseInsensitive Case-insensitive by default.
     * @return boolean
     */
    public function fileExists($file, $caseInsensitive = true)
    {
        if (!$this->isAbsolutePath($file)) {
            $file = $this->basePath().$file;
        }

        if (file_exists($file)) {
            return true;
        }

        if ($caseInsensitive === false) {
            return false;
        }

        $files = glob(dirname($file).DIRECTORY_SEPARATOR.'*', GLOB_NOSORT);
        if ($files) {
            $pattern = preg_quote($file, '#');
            foreach ($files as $f) {
                if (preg_match("#{$pattern}#i", $f)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sanitize a filename by removing characters from a blacklist and escaping dot.
     *
     * @param string $filename The filename to sanitize.
     * @return string The sanitized filename.
     */
    public function sanitizeFilename($filename)
    {
        // Remove blacklisted caharacters
        $blacklist = [ '/', '\\', '\0', '*', ':', '?', '"', '<', '>', '|', '#', '&', '!', '`', ' ' ];
        $filename = str_replace($blacklist, '_', $filename);

        // Avoid hidden file
        $filename = ltrim($filename, '.');

        return $filename;
    }

    /**
     * Render the given file to the given pattern.
     *
     * This method does not rename the given path.
     *
     * @uses   strtr() To replace tokens in the form `{{foobar}}`.
     * @param  string         $from The string being rendered.
     * @param  string         $to   The pattern replacing $from.
     * @param  array|callable $args Extra rename tokens.
     * @throws InvalidArgumentException If the given arguments are invalid.
     * @throws UnexpectedValueException If the renaming failed.
     * @return string Returns the rendered target.
     */
    public function renderFileRenamePattern($from, $to, $args = null)
    {
        if (!is_string($from)) {
            throw new InvalidArgumentException(sprintf(
                'The target to rename must be a string, received %s',
                (is_object($from) ? get_class($from) : gettype($from))
            ));
        }

        if (!is_string($to)) {
            throw new InvalidArgumentException(sprintf(
                'The rename pattern must be a string, received %s',
                (is_object($to) ? get_class($to) : gettype($to))
            ));
        }

        $info = pathinfo($from);
        $args = $this->renamePatternArgs($info, $args);

        $to = strtr($to, $args);
        if (strpos($to, '{{') !== false) {
            preg_match_all('~\{\{\s*(.*?)\s*\}\}~i', $to, $matches);

            throw new UnexpectedValueException(sprintf(
                'The rename pattern failed. Leftover tokens found: %s',
                implode(', ', $matches[1])
            ));
        }

        $to = str_replace($info['basename'], $to, $from);

        return $to;
    }

    /**
     * Generate a new filename from the property.
     *
     * @return string
     */
    public function generateFilename()
    {
        $filename  = $this['label'].' '.date('Y-m-d H-i-s');
        $extension = $this->generateExtension();

        if ($extension) {
            return $filename.'.'.$extension;
        } else {
            return $filename;
        }
    }

    /**
     * Generate a unique filename.
     *
     * @param  string|array $filename The filename to alter.
     * @throws InvalidArgumentException If the given filename is invalid.
     * @return string
     */
    public function generateUniqueFilename($filename)
    {
        if (!is_string($filename) && !is_array($filename)) {
            throw new InvalidArgumentException(sprintf(
                'The target must be a string or an array from [pathfino()], received %s',
                (is_object($filename) ? get_class($filename) : gettype($filename))
            ));
        }

        if (is_string($filename)) {
            $info = pathinfo($filename);
        } else {
            $info = $filename;
        }

        $filename = $info['filename'].'-'.uniqid();

        if (isset($info['extension']) && $info['extension']) {
            $filename .= '.'.$info['extension'];
        }

        return $filename;
    }

    /**
     * Generate the file extension from the property's value.
     *
     * @param  string $file The file to parse.
     * @return string The extension based on the MIME type.
     */
    public function generateExtension($file = null)
    {
        if ($file === null) {
            $file = $this->val();
        }

        // PHP 7.2
        if (is_string($file) && defined('FILEINFO_EXTENSION')) {
            $info = new finfo(FILEINFO_EXTENSION);
            $ext  = $info->file($file);

            if ($ext === '???') {
                return '';
            }

            if (strpos($ext, '/') !== false) {
                $ext = explode('/', $ext);
                $ext = reset($ext);
            }

            return $ext;
        }

        return '';
    }

    /**
     * @return string
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @param string $filesystem The file system.
     * @return self
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    protected function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->basePath = $container['config']['base_path'];
        $this->publicPath = $container['config']['public_path'];
    }
    /**
     * Retrieve the path to the storage directory.
     *
     * @return string
     */
    protected function basePath()
    {
        if ($this['publicAccess']) {
            return $this->publicPath;
        } else {
            return $this->basePath;
        }
    }

    /**
     * Converts a php.ini notation for size to an integer.
     *
     * @param  mixed $size A php.ini notation for size.
     * @throws InvalidArgumentException If the given parameter is invalid.
     * @return integer Returns the size in bytes.
     */
    protected function parseIniSize($size)
    {
        if (is_numeric($size)) {
            return $size;
        }

        if (!is_string($size)) {
            throw new InvalidArgumentException(
                'Size must be an integer (in bytes, e.g.: 1024) or a string (e.g.: 1M).'
            );
        }

        $quant = 'bkmgtpezy';
        $unit = preg_replace('/[^'.$quant.']/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size = ($size * pow(1024, stripos($quant, $unit[0])));
        }

        return round($size);
    }

    /**
     * Determine if the given file path is am absolute path.
     *
     * Note: Adapted from symfony\filesystem.
     *
     * @see https://github.com/symfony/symfony/blob/v3.2.2/LICENSE
     *
     * @param  string $file A file path.
     * @return boolean Returns TRUE if the given path is absolute. Otherwise, returns FALSE.
     */
    protected function isAbsolutePath($file)
    {
        return strspn($file, '/\\', 0, 1)
            || (strlen($file) > 3
                && ctype_alpha($file[0])
                && substr($file, 1, 1) === ':'
                && strspn($file, '/\\', 2, 1))
            || null !== parse_url($file, PHP_URL_SCHEME);
    }

    /**
     * Determine if the given value is a data URI.
     *
     * @param  mixed $val The value to check.
     * @return boolean
     */
    protected function isDataUri($val)
    {
        return is_string($val) && preg_match('/^data:/i', $val);
    }

    /**
     * Determine if the given value is a data array.
     *
     * @param  mixed $val The value to check.
     * @return boolean
     */
    protected function isDataArr($val)
    {
        return is_array($val) && isset($val['id']);
    }

    /**
     * Retrieve the rename pattern tokens for the given file.
     *
     * @param  string|array   $path The string to be parsed or an associative array of information about the file.
     * @param  array|callable $args Extra rename tokens.
     * @throws InvalidArgumentException If the given arguments are invalid.
     * @throws UnexpectedValueException If the given path is invalid.
     * @return string Returns the rendered target.
     */
    private function renamePatternArgs($path, $args = null)
    {
        if (!is_string($path) && !is_array($path)) {
            throw new InvalidArgumentException(sprintf(
                'The target must be a string or an array from [pathfino()], received %s',
                (is_object($path) ? get_class($path) : gettype($path))
            ));
        }

        if (is_string($path)) {
            $info = pathinfo($path);
        } else {
            $info = $path;
        }

        if (!isset($info['basename']) || $info['basename'] === '') {
            throw new UnexpectedValueException(
                'The basename is missing from the target'
            );
        }

        if (!isset($info['filename']) || $info['filename'] === '') {
            throw new UnexpectedValueException(
                'The filename is missing from the target'
            );
        }

        if (!isset($info['extension'])) {
            $info['extension'] = '';
        }

        $defaults = [
            '{{property}}'  => $this->ident(),
            '{{label}}'     => $this['label'],
            '{{extension}}' => $info['extension'],
            '{{basename}}'  => $info['basename'],
            '{{filename}}'  => $info['filename'],
        ];

        if ($args === null) {
            $args = $defaults;
        } else {
            if (is_callable($args)) {
                /**
                 * Rename Arguments Callback Routine
                 *
                 * @param  array             $info Information about the file path from {@see pathinfo()}.
                 * @param  PropertyInterface $prop The related image property.
                 * @return array
                 */
                $args = $args($info, $this);
            }

            if (is_array($args)) {
                $args = array_replace($defaults, $args);
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Arguments must be an array or a callable that returns an array, received %s',
                    (is_object($args) ? get_class($args) : gettype($args))
                ));
            }
        }

        return $args;
    }

    /**
     * Retrieve normalized file upload data for this property.
     *
     * @return array A tree of normalized $_FILE entries.
     */
    public function getUploadedFiles()
    {
        $propIdent = $this->ident();

        $filterErrNoFile = function (array $file) {
            return $file['error'] !== UPLOAD_ERR_NO_FILE;
        };
        $uploadedFiles = static::parseUploadedFiles($_FILES, $filterErrNoFile, $propIdent);

        return $uploadedFiles;
    }

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file data.
     *
     * @link https://github.com/slimphp/Slim/blob/3.12.1/Slim/Http/UploadedFile.php
     *     Adapted from slim/slim.
     *
     * @todo Add support for "dot" notation on $searchKey.
     *
     * @param  array    $uploadedFiles  The non-normalized tree of uploaded file data.
     * @param  callable $filterCallback If specified, the callback function to used to filter files.
     * @param  mixed    $searchKey      If specified, then only top-level keys containing these values are returned.
     * @return array A tree of normalized $_FILE entries.
     */
    public static function parseUploadedFiles(array $uploadedFiles, callable $filterCallback = null, $searchKey = null)
    {
        if ($searchKey !== null) {
            if (is_array($searchKey)) {
                $uploadedFiles = array_intersect_key($uploadedFiles, array_flip($searchKey));
                return static::parseUploadedFiles($uploadedFiles, $filterCallback);
            }

            if (isset($uploadedFiles[$searchKey])) {
                $uploadedFiles = [
                    $searchKey => $uploadedFiles[$searchKey],
                ];
                $parsedFiles = static::parseUploadedFiles($uploadedFiles, $filterCallback);
                if (isset($parsedFiles[$searchKey])) {
                    return $parsedFiles[$searchKey];
                }
            }

            return [];
        }

        $parsedFiles = [];
        foreach ($uploadedFiles as $field => $uploadedFile) {
            if (!isset($uploadedFile['error'])) {
                if (is_array($uploadedFile)) {
                    $subArray = static::parseUploadedFiles($uploadedFile, $filterCallback);
                    if (!empty($subArray)) {
                        if (!isset($parsedFiles[$field])) {
                            $parsedFiles[$field] = [];
                        }

                        $parsedFiles[$field] = $subArray;
                    }
                }
                continue;
            }

            if (!is_array($uploadedFile['error'])) {
                if ($filterCallback === null || $filterCallback($uploadedFile, $field) === true) {
                    if (!isset($parsedFiles[$field])) {
                        $parsedFiles[$field] = [];
                    }

                    $parsedFiles[$field] = [
                        'tmp_name' => $uploadedFile['tmp_name'],
                        'name'     => isset($uploadedFile['name']) ? $uploadedFile['name'] : null,
                        'type'     => isset($uploadedFile['type']) ? $uploadedFile['type'] : null,
                        'size'     => isset($uploadedFile['size']) ? $uploadedFile['size'] : null,
                        'error'    => $uploadedFile['error'],
                    ];
                }
            } else {
                $subArray = [];
                foreach ($uploadedFile['error'] as $fileIdx => $error) {
                    // normalise subarray and re-parse to move the input's keyname up a level
                    $subArray[$fileIdx] = [
                        'tmp_name' => $uploadedFile['tmp_name'][$fileIdx],
                        'name'     => $uploadedFile['name'][$fileIdx],
                        'type'     => $uploadedFile['type'][$fileIdx],
                        'size'     => $uploadedFile['size'][$fileIdx],
                        'error'    => $uploadedFile['error'][$fileIdx],
                    ];

                    $subArray = static::parseUploadedFiles($subArray, $filterCallback);
                    if (!empty($subArray)) {
                        if (!isset($parsedFiles[$field])) {
                            $parsedFiles[$field] = [];
                        }

                        $parsedFiles[$field] = $subArray;
                    }
                }
            }
        }

        return $parsedFiles;
    }

    /**
     * Normalize a file path string so that it can be checked safely.
     *
     * Attempt to avoid invalid encoding bugs by transcoding the path. Then
     * remove any unnecessary path components including '.', '..' and ''.
     *
     * @link https://gist.github.com/thsutton/772287
     *
     * @param  string $path     The path to normalise.
     * @param  string $encoding The name of the path iconv() encoding.
     * @return string The path, normalised.
     */
    public static function normalizePath($path, $encoding = 'UTF-8')
    {
        $key = $path;

        if (isset(static::$normalizePathCache[$key])) {
            return static::$normalizePathCache[$key];
        }

        // Attempt to avoid path encoding problems.
        $path = iconv($encoding, $encoding.'//IGNORE//TRANSLIT', $path);

        if (strpos($path, '..') !== false || strpos($path, './') !== false) {
            // Process the components
            $parts = explode('/', $path);
            $safe = [];
            foreach ($parts as $idx => $part) {
                if ((empty($part) && !is_numeric($part)) || ($part === '.')) {
                    continue;
                } elseif ($part === '..') {
                    array_pop($safe);
                    continue;
                } else {
                    $safe[] = $part;
                }
            }

            // Return the "clean" path
            $path = implode(DIRECTORY_SEPARATOR, $safe);

            if ($key[0] === '/' && $path[0] !== '/') {
                $path = '/'.$path;
            }
        }

        static::$normalizePathCache[$key] = $path;

        return static::$normalizePathCache[$key];
    }
}
