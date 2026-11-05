-- Your bookings moved from the Claude version of the app (sample bookings left out).
-- Import AFTER schema.sql.
SET NAMES utf8mb4;
START TRANSACTION;
INSERT IGNORE INTO bookings (id,guest,phone,room_id,pax,check_in,check_out,check_in_time,check_out_time,rate,extras,discount,disc_type,source,status,notes,bill_no,bill_date) VALUES ('mvwiuqfpdxuif','Raghu','8073496726','r1',2,'2026-10-31','2026-11-01',NULL,NULL,1800,0,0,'amt','Direct / phone','confirmed','',NULL,NULL);
INSERT IGNORE INTO bookings (id,guest,phone,room_id,pax,check_in,check_out,check_in_time,check_out_time,rate,extras,discount,disc_type,source,status,notes,bill_no,bill_date) VALUES ('mvwiy32c6qem6','Chethana K','8073496726','r2',4,'2026-11-02','2026-11-03',NULL,NULL,2500,0,0,'amt','Direct / phone','confirmed','',NULL,NULL);
INSERT IGNORE INTO bookings (id,guest,phone,room_id,pax,check_in,check_out,check_in_time,check_out_time,rate,extras,discount,disc_type,source,status,notes,bill_no,bill_date) VALUES ('mvxxsiuvxm3ed','Nandana','8073496726','r1',5,'2026-11-06','2026-11-08',NULL,NULL,3600,0,0,'amt','Direct / phone','confirmed','',NULL,NULL);
COMMIT;
