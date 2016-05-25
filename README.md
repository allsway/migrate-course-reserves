#####README

######courses.php
Takes as arguments: 
   - a csv file of course records 
   - a csv file of item records

Creates:
  - A course record in Alma for every course record in the CSV file
  - A reading list in Alma associated with each course record in the CSV file
  - A citation for each unique bib record (from the associated item record) in the CSV file item list
  
######delete_courses.php
Removes _all_ courses created by the Alma API.  Use only when testing.  
