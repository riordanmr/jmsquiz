// Program to read a file that contains numbered questions like:
// 28. Following through on a task
// and output INSERT statements like:
// INSERT INTO questions (qnum, qtext) VALUES (23, 'Following through on a task');
//
// MRR  2023-09-29
package main

import (
	"bufio"
	"fmt"
	"os"
	"regexp"
	"strconv"
	"strings"
)

func escape_sql(sqlval string) string {
	escaped := strings.Replace(sqlval, "\\", "", -1)
	escaped = strings.Replace(escaped, "'", "\\'", -1)
	return escaped
}

// Created mostly by ChatGPT:
// https://chat.openai.com/c/fe0bf0c8-c057-471a-9fc6-3ea2d7b08395
func main() {
	// Create a regular expression pattern to match lines with digits followed by a period
	pattern := regexp.MustCompile(`^(\d+)\.(.+)`)

	// Create a scanner to read from standard input
	scanner := bufio.NewScanner(os.Stdin)

	// Process each line of input
	for scanner.Scan() {
		line := scanner.Text()
		// Check if the line matches the pattern
		if matches := pattern.FindStringSubmatch(line); len(matches) == 3 {
			// Extract the quiznum (digits) and question (text)
			quiznum, err := strconv.Atoi(matches[1])
			if err != nil {
				fmt.Println("Error converting digits to integer:", err)
				continue
			}
			question := strings.Trim(matches[2], " ")
			fmt.Printf("INSERT INTO questions (qnum, qtext) VALUES (%d, '%s');\n", quiznum,
				escape_sql(question))
		}
	}

	// Check for any scanner errors
	if err := scanner.Err(); err != nil {
		fmt.Println("Error reading input:", err)
	}
}
