# Easy Coding Standard FileOutput
An error formatter for [Easy Coding Standard](https://github.com/Symplify/EasyCodingStandard) that exports analysis result into HTML file

## Installation
```
composer require noximo/easycodingstandard-fileoutput
```

## Setup
Edit or create your ecs.ym file and register new output formatter. 

- $outputFile - path to file where analysis will be outputted (required)

- $defaultFormatter - specifies which other formatter will be used with FileOutput formatter running silently in the background. You can leave it unsetted if you wish to only work with FileOutput-generated files. (optional)

- $customTemplate - this argument sets custom output template. See [table.phtml](/src/table.phtml) for implementation details and data structure. (optional)
```
services:
  noximo\EasyCodingStandardFileoutput\FileOutputFormatter:
    autowire: true
    arguments:
      $outputFile: './log/test.html'
      $defaultFormatter: '@Symplify\EasyCodingStandard\Console\Output\TableOutputFormatter'
      $customTemplate: './tests/alternative_table.phtml'
```

At the time of writing of this readme these formatters were available by default in ecs:
- ```@Symplify\EasyCodingStandard\Console\Output\TableOutputFormatter```,  
- ```@Symplify\EasyCodingStandard\Console\Output\JsonOutputFormatter```

_Check [Easy Coding Standard repository](https://github.com/Symplify/EasyCodingStandard) for possible updates._ 

## Usage

simply change --output-format argument to *file*:

```
vendor\bin\ecs check someDir --output-format=file
```


## Output
FileOutput will generate HTML file (assuming default template) with hyperlinks directly into PHP files where errors were encountered. If you want to leverage clickable links, set up your enviromenent according to this article: [https://tracy.nette.org/en/open-files-in-ide](https://tracy.nette.org/en/open-files-in-ide)

Note: When you fix an error, file structure and line numbers can no longer correspond to line number at the time of analysis. You'll need to re-run ecs to regenerate output file. Errors are outputed in descending order (line-number wise) so there's bigger chance that you won't lines out of their current positions. 
