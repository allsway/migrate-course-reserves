#####README

######Pre-requsite setup in Alma
   - The SRU integration profile must be set up in Alma prior to running this
   - A default course unit must be set up in Alma and set in the your .ini file
   
######courses.ini

Configuration setup can be modified in the file courses.ini.  

```
apikey = "apikey"
baseurl = "baseurl"
campuscode = "campus code for SRU"
;delimter used in the courses.csv course extract file
delimeter = "|"    
;The name for the course processing department that you would like for 'courses' to use.
;View your current processing deparments under Fulfillment Configuration | Courses | Processing Departments. 
processing_dept = "Course Unit"
```

######courses.php
Takes as arguments: 
   - a csv file of course records 
   - a csv file of item records
   
Run as `php courses.php course_data.csv item_data.csv`

Creates:
  - A course record in Alma for every course record in the CSV file
  - A reading list in Alma associated with each course record in the CSV file
  - A citation for each unique bib record (from the associated item record) in the CSV file item list
  - course_errors.log file, recording any errors with the reading of the CSV file and loading of the courses
  
######delete_courses.php
Removes _all_ courses created by the Alma API.  Use only when testing.  
