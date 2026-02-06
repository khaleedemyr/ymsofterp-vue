-- Kill semua query yang Creating sort index lebih dari 1000 detik
-- JALANKAN DENGAN HATI-HATI!

-- Query untuk melihat process yang akan di-kill
SELECT 
    Id,
    User,
    Host,
    db,
    Command,
    Time,
    State,
    LEFT(Info, 100) as Query_Preview
FROM information_schema.PROCESSLIST
WHERE State = 'Creating sort index' 
  AND Time > 1000
  AND Command = 'Execute';

-- Uncomment baris di bawah untuk execute kill
-- KILL 30057725;
-- KILL 30058254;
-- KILL 30058287;
-- KILL 30058449;
-- KILL 30058452;
-- KILL 30058619;
-- KILL 30058736;
-- KILL 30058773;
-- KILL 30058782;
-- KILL 30058784;
-- KILL 30058785;
-- KILL 30058788;
-- KILL 30058790;
-- KILL 30058794;
-- KILL 30058798;
-- KILL 30058803;
-- KILL 30058805;
-- KILL 30058806;
-- KILL 30058807;

-- Atau gunakan procedure untuk kill otomatis:
DELIMITER $$
CREATE PROCEDURE kill_stuck_queries()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE process_id INT;
    DECLARE cur CURSOR FOR 
        SELECT Id 
        FROM information_schema.PROCESSLIST 
        WHERE State = 'Creating sort index' 
          AND Time > 1000 
          AND Command = 'Execute';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO process_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET @sql = CONCAT('KILL ', process_id);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        SELECT CONCAT('Killed process: ', process_id) as status;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

-- Jalankan procedure
-- CALL kill_stuck_queries();

-- Hapus procedure setelah selesai
-- DROP PROCEDURE IF EXISTS kill_stuck_queries;
